document.addEventListener('DOMContentLoaded', () => {
  const categoryForm = document.getElementById('categoryForm');
  const imageInput = document.getElementById('categoryImage');
  const imagePreview = document.getElementById('imagePreview');
  const previewContainer = document.getElementById('imagePreviewContainer');
  const resetBtn = document.getElementById('resetBtn');
  const saveBtn = document.getElementById('saveBtn');

  // 1. Image Preview Handler
  if (imageInput) {
    imageInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = (event) => {
          imagePreview.src = event.target.result;
          previewContainer.style.display = 'block';
        };
        reader.readAsDataURL(file);
      } else {
        previewContainer.style.display = 'none';
      }
    });
  }

  // 2. Bootstrap 5 Form Validation
  if (categoryForm) {
    categoryForm.addEventListener('submit', (e) => {
      if (!categoryForm.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
      }
      categoryForm.classList.add('was-validated');
    }, false);
  }

  // 3. Reset Form Actions
  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      categoryForm.reset();
      categoryForm.classList.remove('was-validated');
      document.getElementById('categoryId').value = '';
      document.getElementById('formTitle').innerHTML = '<i class="bi bi-plus-circle text-primary me-2"></i>Add New Category';
      
      if (saveBtn) {
        saveBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Save Category';
      }
      
      if (previewContainer) {
        previewContainer.style.display = 'none';
      }
    });
  }

  // 4. Client-side Search Filter for Category Table
  const searchTable = document.getElementById('searchTable');
  if (searchTable) {
    searchTable.addEventListener('keyup', (e) => {
      const value = e.target.value.toLowerCase();
      const rows = document.querySelectorAll('#categoryTableBody tr');
      rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(value) ? '' : 'none';
      });
    });
  }
});

// Helper Function: Fill form for editing
// FIXED: Removed the extra 'parentId' argument to match the PHP HTML
function editCategory(id, name, description, status) {
  document.getElementById('categoryId').value = id;
  document.getElementById('categoryName').value = name;
  document.getElementById('categoryDescription').value = description === '—' ? '' : description;
  document.getElementById('categoryStatus').value = status;
  
  document.getElementById('formTitle').innerHTML = '<i class="bi bi-pencil-square text-warning me-2"></i>Edit Category';
  
  const saveBtn = document.getElementById('saveBtn');
  if (saveBtn) {
    saveBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Update Category';
  }
  
  // Scroll to top smoothly
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Helper Function: Confirm deletion
// Note: If using the HTML <form> approach for delete, this function is no longer called.
function deleteCategory(id) {
  if (confirm('Are you sure you want to delete this category?')) {
    console.log(`Category ID ${id} deleted.`);
  }
}