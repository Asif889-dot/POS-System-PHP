/* =========================================================
   sale.js — Terminal Sale (with Customer-Specific Category Discounts)
   ========================================================= */

let cart = [];

// ---------- Currency (pulled from server-rendered DOM) ----------
const __CUR_EL   = document.getElementById('cartTotal');
const __CUR_TEXT = __CUR_EL ? __CUR_EL.textContent.trim() : 'Rs.0.00';
const CURRENCY   = (__CUR_TEXT.match(/^[^\d.-]+/) || ['Rs.'])[0] || 'Rs.';

const fmt = (n) => CURRENCY + Number(n || 0).toFixed(2);
const esc = (s) => String(s).replace(/[&<>"']/g, c => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
}[c]));

// ---------- DOM refs ----------
const productGrid    = document.getElementById('productGrid');
const searchInput    = document.getElementById('searchInput');
const categoryFilter = document.getElementById('categoryFilter');
const cartTbody      = document.getElementById('cartTableBody');
const totalEl        = document.getElementById('cartTotal');
const paidInput      = document.getElementById('paidAmount');
const paymentMethod  = document.getElementById('paymentMethod');
const customerSelect = document.getElementById('customerSelect');
const checkoutBtn    = document.getElementById('checkoutBtn');
const payTypeRadios  = document.querySelectorAll('input[name="paymentType"]');
const dueDateWrap    = document.getElementById('dueDateWrap');
const dueDateInput   = document.getElementById('dueDate');

const getTotal = () => cart.reduce((s, i) => s + i.price * i.qty, 0);

// ---------- Core Cart Logic ----------
function addToCart(product) {
    const existing = cart.find(i => i.id === product.id);
    
    if (existing) {
        if (existing.qty + 1 > product.stock)
            return alert(`Only ${product.stock} unit(s) of "${product.name}" in stock.`);
        existing.qty += 1;
    } else {
        if (product.stock < 1) return alert('Out of stock.');
        cart.push({
            id: product.id, 
            name: product.name, 
            sku: product.sku,
            category_id: product.category_id, // Needed for discount lookup
            originalPrice: parseFloat(product.price),
            price: parseFloat(product.price), // Will be recalculated
            discountText: '',
            stock: parseInt(product.stock, 10), 
            qty: 1,
        });
    }
    
    // Always recalculate after adding to ensure correct prices
    recalculateCartPrices();
    flashLastRow();
}

function recalculateCartPrices() {
    const customerId = customerSelect ? (customerSelect.value || 'global') : 'global';

    cart.forEach(item => {
        // Find the best discount (Customer specific > Global)
        let rule = null;
        if (CATEGORY_DISCOUNTS[customerId] && CATEGORY_DISCOUNTS[customerId][item.category_id]) {
            rule = CATEGORY_DISCOUNTS[customerId][item.category_id];
        } else if (CATEGORY_DISCOUNTS['global'] && CATEGORY_DISCOUNTS['global'][item.category_id]) {
            rule = CATEGORY_DISCOUNTS['global'][item.category_id];
        }

        let finalPrice = item.originalPrice;
        let discountText = '';

        if (rule) {
            let discountAmount = 0;
            if (rule.discount_type === 'percentage') {
                discountAmount = finalPrice * (parseFloat(rule.discount_value) / 100);
            } else {
                discountAmount = parseFloat(rule.discount_value);
            }
            // Cap discount at product price
            discountAmount = Math.min(discountAmount, finalPrice);
            finalPrice -= discountAmount;

            if (discountAmount > 0) {
                discountText = `(${rule.discount_type === 'percentage' ? rule.discount_value+'% Off' : 'Rs.'+rule.discount_value+' Off'})`;
            }
        }

        item.price = finalPrice;
        item.discountText = discountText;
    });

    renderCart();
}

function flashLastRow() {
    const last = cartTbody.querySelector('tr:last-child');
    if (!last || last.id === 'emptyCartRow') return;
    last.classList.add('flash');
    setTimeout(() => last.classList.remove('flash'), 800);
}

// ---------- Render cart ----------
function renderCart() {
    if (cart.length === 0) {
        cartTbody.innerHTML =
            '<tr id="emptyCartRow"><td colspan="4" class="text-center text-muted py-4">Cart is empty</td></tr>';
        totalEl.textContent = fmt(0);
        updatePaymentPreview();
        updateCheckoutState();
        return;
    }

    cartTbody.innerHTML = cart.map((item, idx) => `
        <tr data-idx="${idx}">
            <td>
                <span class="cart-item-name">${esc(item.name)}</span>
                <span class="cart-item-price">
                    ${item.originalPrice > item.price 
                        ? `<del class="text-danger">${fmt(item.originalPrice)}</del> ${fmt(item.price)} <small class="text-success fw-bold">${item.discountText}</small>` 
                        : `${fmt(item.price)} each`}
                </span>
            </td>
            <td class="text-center">
                <input type="number" class="form-control qty-input"
                       min="1" max="${item.stock}" value="${item.qty}"
                       onchange="updateQty(${idx}, this.value)">
            </td>
            <td class="text-end cart-subtotal">${fmt(item.price * item.qty)}</td>
            <td class="text-end">
                <button type="button" class="btn btn-remove" onclick="removeItem(${idx})">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>
        </tr>
    `).join('');

    totalEl.textContent = fmt(getTotal());
    updatePaymentPreview();
    updateCheckoutState();
}

function updateQty(idx, val) {
    cart[idx].qty = Math.max(1, Math.min(parseInt(val, 10) || 1, cart[idx].stock));
    renderCart();
}
function removeItem(idx) { cart.splice(idx, 1); renderCart(); }
function clearCart() {
    if (!cart.length || !confirm('Clear the current cart?')) return;
    cart = [];
    renderCart();
    paidInput.value = '';
    const row = document.getElementById('previewRow');
    if (row) row.remove();
}

// ---------- Payment preview ----------
function getPreviewRow() {
    let row = document.getElementById('previewRow');
    if (!row) {
        row = document.createElement('div');
        row.id = 'previewRow';
        row.className = 'mb-2';
        checkoutBtn.parentElement.parentElement.insertBefore(row, checkoutBtn.parentElement);
    }
    return row;
}

function updatePaymentPreview() {
    const total = getTotal();
    const paid  = parseFloat(paidInput.value) || 0;
    const diff  = paid - total;
    const row   = document.getElementById('previewRow');

    if (cart.length === 0 && paid === 0) { if (row) row.remove(); return; }

    const r = getPreviewRow();
    if (diff >= 0) {
        r.innerHTML = `<div class="d-flex justify-content-between" id="changeRow">
            <span>Change:</span><span id="changeAmount">${fmt(diff)}</span></div>`;
    } else {
        r.innerHTML = `<div class="d-flex justify-content-between" id="dueRow"
                            style="background:#fff3cd;color:#664d03;padding:.35rem .6rem;border-radius:.4rem;">
            <span>Due (Udhar):</span><span>${fmt(Math.abs(diff))}</span></div>`;
    }
}

// ---------- Checkout state ----------
function getPaymentType() {
    const checked = document.querySelector('input[name="paymentType"]:checked');
    return checked ? checked.value : 'paid';
}

function updateCheckoutState() {
    const total = getTotal();
    const paid  = parseFloat(paidInput.value) || 0;
    const type  = getPaymentType();
    const customer = customerSelect ? customerSelect.value : '';

    if (dueDateWrap) dueDateWrap.style.display = (type === 'udhar' || type === 'partial') ? '' : 'none';

    let enabled = cart.length > 0;
    if (type === 'paid')    enabled = enabled && paid >= total;
    if (type === 'partial') enabled = enabled && paid > 0 && paid < total && customer !== '';
    if (type === 'udhar')   enabled = enabled && paid === 0 && customer !== '';

    checkoutBtn.disabled = !enabled;
}

// ---------- Event Listeners ----------
function applyFilter() {
    const q   = searchInput.value.trim().toLowerCase();
    const cat = categoryFilter.value;
    document.querySelectorAll('.product-item').forEach(el => {
        const okName = !q || el.dataset.name.includes(q) || el.dataset.sku.includes(q);
        const okCat  = !cat || el.dataset.category === cat;
        el.style.display = (okName && okCat) ? '' : 'none';
    });
}
searchInput?.addEventListener('input', applyFilter);
categoryFilter?.addEventListener('change', applyFilter);
paidInput?.addEventListener('input', () => { updatePaymentPreview(); updateCheckoutState(); });

// When the customer changes, re-calculate the cart prices to apply their specific discounts!
customerSelect?.addEventListener('change', () => {
    recalculateCartPrices();
    updateCheckoutState();
});

payTypeRadios.forEach(r => r.addEventListener('change', () => {
    if (r.checked && r.value === 'udhar') paidInput.value = '0';
    updatePaymentPreview();
    updateCheckoutState();
}));

// ---------- Checkout ----------
async function processCheckout() {
    const total    = getTotal();
    const paid     = parseFloat(paidInput.value) || 0;
    const type     = getPaymentType();
    const customer = customerSelect && customerSelect.value ? parseInt(customerSelect.value, 10) : null;

    if (cart.length === 0) return alert('Cart is empty.');
    if ((type === 'udhar' || type === 'partial') && !customer)
        return alert('Please select a customer for credit (udhar) sales.');
    if (type === 'paid' && paid < total)
        return alert('Paid amount is less than total for a paid sale.');
    if (type === 'partial' && (paid <= 0 || paid >= total))
        return alert('Partial payment must be more than 0 and less than total.');
    if (type === 'udhar' && paid !== 0)
        return alert('Udhar sale must have paid amount = 0.');

    checkoutBtn.disabled = true;
    const original = checkoutBtn.innerHTML;
    checkoutBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing…';

    try {
        const payload = {
            items: cart.map(i => ({ product_id: i.id, quantity: i.qty, unit_price: i.price })),
            total_amount: total,
            paid_amount: paid,
            payment_method: paymentMethod.value,
            customer_id: customer,
            due_date: dueDateInput && dueDateInput.value ? dueDateInput.value : null,
        };

        const res    = await fetch('../../backend/sale/process_sale.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const result = await res.json();

        if (!result.success) {
            alert('Sale failed: ' + (result.message || 'Unknown error'));
            checkoutBtn.disabled = false;
            checkoutBtn.innerHTML = original;
            return;
        }

        showToast(`${result.data.payment_type === 'paid' ? 'Sale' : 'Credit sale'} ${result.data.invoice_no}` +
                  (result.data.due_amount > 0 ? ` — Due: ${fmt(result.data.due_amount)}` : ''));

        openBillCustomizer(result.data);
        resetSale(original);

    } catch (err) {
        console.error(err);
        alert('Checkout Error: ' + err.message);
        checkoutBtn.disabled = false;
        checkoutBtn.innerHTML = original;
    }
}

function resetSale(originalHTML) {
    cart = [];
    renderCart();
    paidInput.value = '';
    paymentMethod.value = 'cash';
    if (customerSelect) customerSelect.value = '';
    if (dueDateInput) dueDateInput.value = '';
    document.querySelectorAll('input[name="paymentType"]').forEach((r, i) => r.checked = (i === 0));
    checkoutBtn.disabled = true;
    checkoutBtn.innerHTML = originalHTML || '<i class="bi bi-check2-circle me-1"></i> Complete Sale';
    const row = document.getElementById('previewRow');
    if (row) row.remove();
}

// ---------- Toast ----------
function showToast(msg) {
    const t = document.createElement('div');
    t.className = 'sale-toast';
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(() => t.classList.add('show'));
    setTimeout(() => {
        t.classList.remove('show');
        setTimeout(() => t.remove(), 300);
    }, 3500);
}

/* =========================================================
   BILL CUSTOMIZER
   ========================================================= */

let currentBillData = null;
let billModal = null;
let customEdits = {}; 

function openBillCustomizer(data) {
    currentBillData = data;
    const s = data.settings || {};
    
    customEdits = {
        orgName: s.org_name || 'My Store',
        orgAddr: s.org_address || '',
        orgPhone: s.org_phone || '',
        thankYou: s.bill_thank_you || 'Thank you for shopping with us!',
        footerNote: s.bill_footer_note || ''
    };

    document.getElementById('billEditMode').checked = false;
    document.getElementById('billPaperSize').value = s.bill_paper || 'a4';
    document.querySelectorAll('input[name="billTheme"]').forEach(r => r.checked = (r.value === (s.bill_theme || 'classic')));
    document.getElementById('billShowLogo').checked     = (s.bill_show_logo === '1');
    document.getElementById('billShowHeader').checked   = (s.bill_show_header === '1');
    document.getElementById('billShowQR').checked       = (s.bill_show_qr === '1');
    document.getElementById('billShowDateTime').checked = (s.bill_show_datetime === '1');
    document.getElementById('billShowCashier').checked  = (s.bill_show_cashier === '1');
    document.getElementById('billShowPrices').checked   = (s.bill_show_prices === '1');
    document.getElementById('billThankYou').value       = customEdits.thankYou;
    document.getElementById('billFooterNote').value     = customEdits.footerNote;

    if (!billModal) billModal = new bootstrap.Modal(document.getElementById('billCustomizeModal'));

    if (!openBillCustomizer._wired) {
        openBillCustomizer._wired = true;
        
        ['billTheme','billPaperSize','billShowLogo','billShowHeader','billShowQR','billShowDateTime','billShowCashier','billShowPrices'].forEach(id => {
            const el = document.getElementById(id);
            if (el) { el.addEventListener('change', renderBillPreview); }
        });
        
        document.getElementById('billThankYou').addEventListener('input', (e) => {
            customEdits.thankYou = e.target.value;
            renderBillPreview();
        });
        document.getElementById('billFooterNote').addEventListener('input', (e) => {
            customEdits.footerNote = e.target.value;
            renderBillPreview();
        });
        
        document.getElementById('billEditMode').addEventListener('change', () => renderBillPreview());

        document.getElementById('billPrintBtn').addEventListener('click', () => printCustomizedBill('print'));
        document.getElementById('billPreviewPdfBtn').addEventListener('click', () => printCustomizedBill('pdf'));
        
        document.getElementById('billSaveTemplateBtn')?.addEventListener('click', async function() {
            const btn = this; const original = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving…';

            const opts = readBillOptions();
            const payload = {
                bill_theme: opts.theme, bill_paper: opts.paper,
                bill_show_logo: opts.showLogo, bill_show_header: opts.showHeader,
                bill_show_qr: opts.showQR, bill_show_datetime: opts.showDateTime,
                bill_show_cashier: opts.showCashier, bill_show_prices: opts.showPrices,
                bill_thank_you: opts.thankYou, bill_footer_note: opts.footerNote
            };

            try {
                const res = await fetch('../../backend/settings/save_template.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
                });
                const result = await res.json();
                if (result.success) {
                    btn.innerHTML = '<i class="bi bi-check2"></i> Saved!';
                    btn.classList.replace('btn-outline-success', 'btn-success');
                    setTimeout(() => { btn.innerHTML = original; btn.classList.replace('btn-success', 'btn-outline-success'); btn.disabled = false; }, 2000);
                } else { alert(result.message); btn.innerHTML = original; btn.disabled = false; }
            } catch (e) { alert('Error saving template.'); btn.innerHTML = original; btn.disabled = false; }
        });
    }

    renderBillPreview();
    billModal.show();
}

function readBillOptions() {
    return {
        theme: document.querySelector('input[name="billTheme"]:checked').value,
        paper: document.getElementById('billPaperSize').value,
        showLogo: document.getElementById('billShowLogo').checked,
        showHeader: document.getElementById('billShowHeader').checked,
        showQR: document.getElementById('billShowQR').checked,
        showDateTime: document.getElementById('billShowDateTime').checked,
        showCashier: document.getElementById('billShowCashier').checked,
        showPrices: document.getElementById('billShowPrices').checked,
        thankYou: document.getElementById('billThankYou').value.trim(),
        footerNote: document.getElementById('billFooterNote').value.trim(),
        isEditing: document.getElementById('billEditMode').checked
    };
}

function buildBillHTML(data, opts) {
    const s         = data.settings || {};
    
    const orgName   = opts.isEditing ? customEdits.orgName : (s.org_name || 'My Store');
    const orgAddr   = opts.isEditing ? customEdits.orgAddr : (s.org_address || '');
    const orgPhone  = opts.isEditing ? customEdits.orgPhone : (s.org_phone || '');
    const orgLogo   = s.org_logo || '';
    
    const cur       = s.currency_symbol || CURRENCY || 'Rs.';
    const curPos    = s.currency_position || 'before';

    const money = v => curPos === 'before' ? cur + Number(v).toFixed(2) : Number(v).toFixed(2) + ' ' + cur;

    const themes = {
        classic: { fontFamily: 'Georgia, serif', headerBg: '#f8f9fa', accent: '#000' },
        modern:  { fontFamily: 'system-ui, sans-serif', headerBg: '#0d6efd', accent: '#0d6efd', headerText: '#fff' },
        minimal: { fontFamily: 'system-ui, sans-serif', headerBg: '#fff', accent: '#495057' },
        bold:    { fontFamily: 'system-ui, sans-serif', headerBg: '#dc3545', accent: '#dc3545', headerText: '#fff', fontWeight: 600 },
        elegant: { fontFamily: 'Georgia, serif', headerBg: '#fdfcf8', accent: '#b89440', headerText: '#5a4a1f' },
        wholesale:{ fontFamily: 'Arial, sans-serif', headerBg: '#fff', accent: '#333', headerText: '#000', isWholesale: true },
    };
    const t = themes[opts.theme] || themes.classic;

    const paperWidths = { 'a4': '100%', 'thermal-80': '300px', 'thermal-58': '220px' };
    const previewWidth = opts.theme === 'wholesale' ? '100%' : paperWidths[opts.paper];

    const itemRows = (data.items || []).map(it => `
        <tr>
            <td style="padding: 4px 0;">${esc(it.name)}</td>
            <td style="padding: 4px 0; text-align: center;">${it.qty}</td>
            ${opts.showPrices ? `<td style="padding: 4px 0; text-align: right;">${money(it.price)}</td><td style="padding: 4px 0; text-align: right;">${money(it.subtotal)}</td>` : ''}
        </tr>
    `).join('');

    const qrId = 'qr_' + Math.random().toString(36).substr(2, 9);
    const qrBlock = opts.showQR ? `
        <div style="text-align: center; margin-top: 10px;">
            <div style="display: inline-block; padding: 8px; background: #fff; border: 1px solid #ccc;">
                <div id="${qrId}" style="display: inline-block;"></div>
                <div style="font-size: 8px; color: #666; margin-top: 4px; letter-spacing: 1px;">SCAN TO VERIFY</div>
            </div>
        </div>
    ` : '';

    if (t.isWholesale) {
        return `
            <div class="bill-preview" style="
                font-family: ${t.fontFamily}; background: #fff; padding: 30px; width: ${previewWidth};
                max-width: 100%; margin: 0 auto; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,.08);
                font-size: 12px; color: #333;
            ">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 2px solid #ccc; padding-bottom: 10px;">
                    <div style="width: 60%;">
                        ${opts.showLogo && orgLogo ? `<img src="../../${esc(orgLogo)}" alt="Logo" style="max-height: 60px; margin-bottom: 10px;"><br>` : ''}
                        
                        ${opts.isEditing 
                            ? `<input type="text" id="editOrgName" value="${esc(orgName)}" class="form-control mb-2 fw-bold" style="font-size:22px;" oninput="updateCustomEdit('orgName', this.value)">`
                            : `<div style="font-size: 22px; font-weight: bold;">${esc(orgName)}</div>`}
                        
                        ${opts.isEditing ? `
                            <input type="text" id="editOrgAddr" value="${esc(orgAddr)}" class="form-control mb-1" placeholder="Address" oninput="updateCustomEdit('orgAddr', this.value)">
                            <input type="text" id="editOrgPhone" value="${esc(orgPhone)}" class="form-control" placeholder="Phone" oninput="updateCustomEdit('orgPhone', this.value)">
                        ` : `
                            <div style="font-size: 11px; color: #666; margin-top: 4px;">${esc(orgAddr)}<br>${esc(orgPhone)}</div>
                        `}
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 28px; font-weight: bold; color: #f97316; text-transform: uppercase;">Invoice</div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <div style="width: 50%; border: 1px solid #ccc; padding: 10px;">
                        <div style="font-weight: bold; font-size: 14px; margin-bottom: 4px;">${esc(data.customer_name || 'Walk-in Customer')}</div>
                        ${data.customer_id ? `<div style="color: #666; font-weight: bold;">Customer Code : ${data.customer_id}</div>` : ''}
                    </div>
                    <div style="width: 45%;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="border: 1px solid #ccc; padding: 6px; font-weight: bold; background: #f9f9f9; width: 40%;">Date</td>
                                <td style="border: 1px solid #ccc; padding: 6px; text-align: right;">${new Date().toLocaleDateString('en-GB')}</td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #ccc; padding: 6px; font-weight: bold; background: #f9f9f9;">Invoice #</td>
                                <td style="border: 1px solid #ccc; padding: 6px; text-align: right;">${esc(data.invoice_no)}</td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #ccc; padding: 6px; font-weight: bold; background: #f9f9f9;">Sale Men</td>
                                <td style="border: 1px solid #ccc; padding: 6px; text-align: right;">${esc(s.cashier_name || 'Admin')}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                    <thead>
                        <tr style="background: #e5e7eb;">
                            <th style="border: 1px solid #ccc; padding: 8px; text-align: left; width: 10%;">Qty</th>
                            <th style="border: 1px solid #ccc; padding: 8px; text-align: left;">Description</th>
                            ${opts.showPrices ? `<th style="border: 1px solid #ccc; padding: 8px; text-align: right; width: 20%;">Unit Price</th><th style="border: 1px solid #ccc; padding: 8px; text-align: right; width: 20%;">Amount</th>` : ''}
                        </tr>
                    </thead>
                    <tbody>
                        ${(data.items || []).map(it => `
                            <tr>
                                <td style="border: 1px solid #ccc; padding: 8px;">${it.qty}</td>
                                <td style="border: 1px solid #ccc; padding: 8px;">${esc(it.name)}</td>
                                ${opts.showPrices ? `<td style="border: 1px solid #ccc; padding: 8px; text-align: right;">${money(it.price)}</td><td style="border: 1px solid #ccc; padding: 8px; text-align: right;">${money(it.subtotal)}</td>` : ''}
                            </tr>
                        `).join('')}
                    </tbody>
                </table>

                <div style="display: flex; justify-content: space-between;">
                    <div style="width: 55%; font-size: 10px; color: #555; border: 1px solid #ccc; padding: 10px;">
                        ${opts.isEditing 
                            ? `<textarea class="form-control form-control-sm" rows="3" oninput="updateCustomEdit('footerNote', this.value)">${esc(opts.footerNote || opts.thankYou)}</textarea>`
                            : `${esc(opts.footerNote || opts.thankYou || 'Thank you for your business!')}`
                        }
                    </div>
                    <div style="width: 40%;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <tr>
                                <td style="padding: 6px; font-weight: bold; text-align: right;">Total:</td>
                                <td style="padding: 6px; text-align: right; font-weight: bold;">${money(data.total_amount)}</td>
                            </tr>
                            <tr>
                                <td style="padding: 6px; text-align: right;">Paid:</td>
                                <td style="padding: 6px; text-align: right;">${money(data.paid_amount)}</td>
                            </tr>
                            <tr>
                                <td style="padding: 6px; text-align: right; font-weight: bold; color: #f97316; font-size: 16px;">Balance due:</td>
                                <td style="padding: 6px; text-align: right; font-weight: bold; color: #f97316; font-size: 16px;">${money(data.due_amount)}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <script>
                if (document.getElementById('${qrId}') && typeof QRCode !== 'undefined') {
                    new QRCode(document.getElementById('${qrId}'), { text: "${esc(data.invoice_no)}", width: 64, height: 64 });
                }
            <\/script>
        `;
    }

    const cashierLine = opts.showCashier ? `<div style="font-size: 10px; color: #666; margin-top: 4px;">Served by: ${esc(s.cashier_name || 'Cashier #1')}</div>` : '';

    const headerBlock = opts.showHeader ? `
        <div style="background: ${t.headerBg}; color: ${t.headerText || '#000'}; padding: 12px 16px; text-align: center; margin: -14px -14px 12px -14px; ${t.headerText ? 'border-radius: 6px 6px 0 0;' : ''}">
            ${opts.showLogo 
                ? (orgLogo ? `<img src="../../${esc(orgLogo)}" alt="Logo" style="max-height: 40px; margin-bottom: 6px;">` : `<div style="margin-bottom: 6px;"><i class="bi bi-shop" style="font-size: 22px;"></i></div>`) 
                : ''}
            
            ${opts.isEditing 
                ? `<input type="text" value="${esc(orgName)}" class="form-control form-control-sm mx-auto mb-1 text-center fw-bold" style="max-width:200px;" oninput="updateCustomEdit('orgName', this.value)">`
                : `<div style="font-size: 16px; font-weight: 700; letter-spacing: .5px;">${esc(orgName)}</div>`}
            
            ${opts.isEditing ? `
                <input type="text" value="${esc(orgAddr)}" class="form-control form-control-sm mx-auto mb-1 text-center" style="max-width:200px; font-size:10px;" placeholder="Address" oninput="updateCustomEdit('orgAddr', this.value)">
                <input type="text" value="${esc(orgPhone)}" class="form-control form-control-sm mx-auto text-center" style="max-width:200px; font-size:10px;" placeholder="Phone" oninput="updateCustomEdit('orgPhone', this.value)">
            ` : `
                ${orgAddr  ? `<div style="font-size: 10px; opacity: .8;">${esc(orgAddr)}</div>` : ''}
                ${orgPhone ? `<div style="font-size: 10px; opacity: .8;">${esc(orgPhone)}</div>` : ''}
            `}
        </div>
    ` : '';

    const dtBlock = opts.showDateTime ? `
        <div style="display: flex; justify-content: space-between; font-size: 10px; color: #666; margin-bottom: 8px;">
            <span>${new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}</span>
            <span>${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
        </div>${cashierLine}
    ` : '';

    const totalsRow = `
        <tr style="border-top: 1px dashed ${t.accent};">
            <td colspan="2" style="padding: 6px 0; font-weight: 700;">TOTAL</td>
            ${opts.showPrices ? `<td colspan="2" style="padding: 6px 0; text-align: right; font-weight: 700; color: ${t.accent};">${money(data.total_amount)}</td>` : ''}
        </tr>
        <tr>
            <td colspan="2" style="padding: 2px 0; font-size: 10px;">Paid</td>
            ${opts.showPrices ? `<td colspan="2" style="padding: 2px 0; text-align: right; font-size: 10px;">${money(data.paid_amount)}</td>` : ''}
        </tr>
        ${data.change_amount > 0 ? `<tr><td colspan="2" style="padding: 2px 0; font-size: 10px;">Change</td>${opts.showPrices ? `<td colspan="2" style="padding: 2px 0; text-align: right; font-size: 10px;">${money(data.change_amount)}</td>` : ''}</tr>` : ''}
        ${data.due_amount > 0 ? `<tr><td colspan="2" style="padding: 2px 0; font-size: 10px; color: #b91c1c;">Due</td>${opts.showPrices ? `<td colspan="2" style="padding: 2px 0; text-align: right; font-size: 10px; color: #b91c1c; font-weight: 600;">${money(data.due_amount)}</td>` : ''}</tr>` : ''}
    `;

    const billedTo = data.customer_id ? `<br><span style="font-size: 9px;"><strong>Billed To:</strong> ${esc(data.customer_name)} (Code: ${data.customer_id})</span>` : '';

    return `
        <div class="bill-preview" style="font-family: ${t.fontFamily}; font-weight: ${t.fontWeight || 400}; background: #fff; padding: 14px; width: ${previewWidth}; max-width: 100%; margin: 0 auto; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,.08); font-size: 11px;">
            ${headerBlock}
            <div style="text-align: center; font-size: 10px; color: #666; margin-bottom: 6px;">Invoice: <strong>${esc(data.invoice_no)}</strong>${billedTo}</div>
            ${dtBlock}
            <table style="width: 100%; border-collapse: collapse; font-size: 10px; margin: 6px 0;">
                <thead><tr style="border-bottom: 1px solid ${t.accent}; font-size: 9px; text-transform: uppercase; letter-spacing: .5px;">
                    <th style="text-align: left; padding: 4px 0;">Item</th><th style="text-align: center; padding: 4px 0;">Qty</th>
                    ${opts.showPrices ? `<th style="text-align: right; padding: 4px 0;">Price</th><th style="text-align: right; padding: 4px 0;">Subtotal</th>` : ''}
                </tr></thead>
                <tbody>${itemRows}</tbody>
                <tfoot>${totalsRow}</tfoot>
            </table>
            ${qrBlock}
            <div style="text-align: center; margin-top: 14px; padding-top: 8px; border-top: 1px dashed ${t.accent};">
                ${opts.isEditing 
                    ? `<input type="text" value="${esc(opts.thankYou)}" class="form-control form-control-sm text-center mb-1" placeholder="Thank you message" oninput="updateCustomEdit('thankYou', this.value)">
                       <input type="text" value="${esc(opts.footerNote)}" class="form-control form-control-sm text-center" style="font-size:10px;" placeholder="Footer note" oninput="updateCustomEdit('footerNote', this.value)">`
                    : `
                        ${opts.thankYou ? `<div style="font-size: 11px; color: ${t.accent}; font-weight: 600;">${esc(opts.thankYou)}</div>` : ''}
                        ${opts.footerNote ? `<div style="font-size: 9px; color: #666; margin-top: 4px;">${esc(opts.footerNote)}</div>` : ''}
                    `
                }
            </div>
        </div>
        <script>
            if (document.getElementById('${qrId}') && typeof QRCode !== 'undefined') {
                new QRCode(document.getElementById('${qrId}'), { text: "${esc(data.invoice_no)}", width: 64, height: 64 });
            }
        <\/script>
    `;
}

window.updateCustomEdit = function(field, value) {
    customEdits[field] = value;
}

function renderBillPreview() {
    if (!currentBillData) return;
    const html = buildBillHTML(currentBillData, readBillOptions());
    const container = document.getElementById('billPreviewContainer');
    container.innerHTML = html;
    
    container.querySelectorAll('script').forEach(old => {
        const s = document.createElement('script');
        Array.from(old.attributes).forEach(a => s.setAttribute(a.name, a.value));
        s.appendChild(document.createTextNode(old.innerHTML));
        old.parentNode.replaceChild(s, old);
    });
}

function printCustomizedBill(mode) {
    if (!currentBillData) return;
    const opts = readBillOptions();
    opts.isEditing = false; 
    const html = buildBillHTML(currentBillData, opts);
    
    const w = window.open('', '_blank', 'width=900,height=800');
    if (!w) return alert('Pop-up blocked.');

    const pageCSS = {
        'a4': `@page { size: A4; margin: 12mm; } body { padding: 20px; }`,
        'thermal-80': `@page { size: 80mm auto; margin: 4mm; } body { padding: 6px; width: 72mm; }`,
        'thermal-58': `@page { size: 58mm auto; margin: 3mm; } body { padding: 4px; width: 52mm; }`,
    }[opts.paper] || '';

    w.document.write(`
        <!DOCTYPE html><html><head><title>Bill ${esc(currentBillData.invoice_no)}</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"><\/script>
        <style>* { box-sizing: border-box; } body { margin: 0; font-family: system-ui; background: #fff; } @media screen { body { background: #f5f6f8; padding: 20px; } } ${pageCSS}</style>
        </head><body>
            ${html}
            ${mode === 'print' ? `<script>setTimeout(() => { window.print(); }, 500);<\/script>` : ''}
        </body></html>
    `);
    w.document.close();
}

Object.assign(window, { addToCart, updateQty, removeItem, clearCart, processCheckout, openBillCustomizer, renderBillPreview, printCustomizedBill });

document.addEventListener('DOMContentLoaded', () => {
    renderCart(); updatePaymentPreview(); updateCheckoutState();
});