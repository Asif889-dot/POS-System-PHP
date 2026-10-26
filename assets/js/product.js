// Image preview logic
document.getElementById('productImage').addEventListener('change', function (e) {
  const file = e.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function (e) {
      document.getElementById('imagePreview').src = e.target.result;
      document.getElementById('imagePreviewContainer').style.display = 'block';
    };
    reader.readAsDataURL(file);
  }
});

// Populate Form for Editing
function editProduct(product) {
  document.getElementById('formTitle').innerHTML = '<i class="bi bi-pencil-square text-primary me-2"></i>Edit Product';
  document.getElementById('productId').value = product.id;
  document.getElementById('productName').value = product.name;
  document.getElementById('categoryIdSelect').value = product.category_id;
  document.getElementById('sku').value = product.sku;
  document.getElementById('price').value = product.price;
  document.getElementById('stock').value = product.stock;
  document.getElementById('description').value = product.description || '';
  document.getElementById('status').value = product.status;

  if (product.image) {
    document.getElementById('imagePreview').src = '../../' + product.image;
    document.getElementById('imagePreviewContainer').style.display = 'block';
  } else {
    document.getElementById('imagePreviewContainer').style.display = 'none';
  }
}

// Reset Form State
function resetProductForm() {
  document.getElementById('formTitle').innerHTML = '<i class="bi bi-box-seam text-primary me-2"></i>Add New Product';
  document.getElementById('productForm').reset();
  document.getElementById('productId').value = '';
  document.getElementById('imagePreviewContainer').style.display = 'none';
}