<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . "/../../config/conn.php";
/** @var mysqli $conn */

$usersResult = $conn->query("SELECT * FROM users ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee & User Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
</head>
<body class="bg-light">

<div class="d-flex">
  <?php include_once "../../config/sidebar.php"; ?>

  <main class="flex-grow-1 p-3 p-md-4 main-content">
    
    <header class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
      <div>
        <h2 class="fw-bold text-dark mb-1">Employee & User Management</h2>
        <p class="text-muted mb-0">Control system access, create employee accounts, and manage organization roles.</p>
      </div>
      <button class="btn btn-primary" onclick="openUserModal()"><i class="bi bi-person-plus me-1"></i> Add New Employee</button>
    </header>

    <!-- Users List Table -->
    <div class="card border-0 shadow-sm rounded-3">
      <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">User Details</th>
              <th>Organization</th>
              <th>Contact Info</th>
              <th>Role</th>
              <th>Status</th>
              <th class="text-end pe-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($usersResult && $usersResult->num_rows > 0): ?>
              <?php while ($user = $usersResult->fetch_assoc()): ?>
                <tr>
                  <td class="ps-3">
                    <div class="d-flex align-items-center">
                      <div class="bg-primary-subtle text-primary fw-bold rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 38px; height: 38px;">
                        <?= strtoupper(substr($user['username'], 0, 1)); ?>
                      </div>
                      <div>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($user['username']); ?></div>
                        <small class="text-muted"><?= htmlspecialchars($user['email']); ?></small>
                      </div>
                    </div>
                  </td>
                  <td><span class="fw-semibold text-secondary"><?= htmlspecialchars($user['organization']); ?></span></td>
                  <td>
                    <small class="d-block"><i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($user['phone'] ?? 'N/A'); ?></small>
                    <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($user['address'] ?? 'N/A'); ?></small>
                  </td>
                  <td>
                    <span class="badge <?= $user['role'] === 'admin' ? 'bg-danger-subtle text-danger' : 'bg-info-subtle text-info'; ?>">
                      <?= ucfirst($user['role']); ?>
                    </span>
                  </td>
                  <td>
                    <?= isset($user['status']) && $user['status'] == 1 
                      ? '<span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Active</span>' 
                      : '<span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-dash-circle me-1"></i>Suspended</span>'; ?>
                  </td>
                  <td class="text-end pe-3">
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editUser(<?= $user['id']; ?>)">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?= $user['id']; ?>, '<?= htmlspecialchars($user['username']); ?>')">
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="6" class="text-center py-4 text-muted">No users registered yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<!-- Add/Edit Employee Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="userModalTitle">Add Employee</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="userForm">
        <div class="modal-body">
          <input type="hidden" id="userId" name="user_id">

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-semibold">Username *</label>
              <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Organization *</label>
              <input type="text" class="form-control" id="organization" name="organization" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Email *</label>
            <input type="email" class="form-control" id="email" name="email" required>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-semibold">Phone</label>
              <input type="text" class="form-control" id="phone" name="phone">
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Role</label>
              <select class="form-select" id="role" name="role">
                <option value="user">User (Staff)</option>
                <option value="admin">Administrator</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Address</label>
            <input type="text" class="form-control" id="address" name="address">
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-semibold">Account Status</label>
              <select class="form-select" id="status" name="status">
                <option value="1">Active</option>
                <option value="0">Suspended</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Password</label>
              <input type="password" class="form-control" id="password" name="password" placeholder="Min 6 characters">
            </div>
          </div>

        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Account</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let modalInstance = null;

document.addEventListener('DOMContentLoaded', () => {
  modalInstance = new bootstrap.Modal(document.getElementById('userModal'));
});

function openUserModal() {
  document.getElementById('userForm').reset();
  document.getElementById('userId').value = '';
  document.getElementById('userModalTitle').innerText = 'Add Employee Account';
  document.getElementById('password').placeholder = 'Required for new user';
  modalInstance.show();
}

// Fetch user data & populate modal
async function editUser(id) {
  try {
    const res = await fetch(`../../backend/employee/user_actions.php?action=get&id=${id}`);
    const result = await res.json();

    if (result.success) {
      const u = result.data;
      document.getElementById('userId').value = u.id;
      document.getElementById('username').value = u.username;
      document.getElementById('organization').value = u.organization;
      document.getElementById('email').value = u.email;
      document.getElementById('phone').value = u.phone || '';
      document.getElementById('address').value = u.address || '';
      document.getElementById('role').value = u.role;
      document.getElementById('status').value = u.status ?? 1;
      document.getElementById('password').value = '';
      document.getElementById('password').placeholder = 'Leave blank to keep current';

      document.getElementById('userModalTitle').innerText = 'Edit Employee Account';
      modalInstance.show();
    } else {
      alert(result.message);
    }
  } catch (err) {
    alert('Failed to load user details.');
  }
}

// Form Submission via AJAX
document.getElementById('userForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);

  try {
    const res = await fetch('../../backend/employee/user_actions.php?action=save', {
      method: 'POST',
      body: formData
    });
    const result = await res.json();

    if (result.success) {
      alert(result.message);
      modalInstance.hide();
      location.reload();
    } else {
      alert('Error: ' + result.message);
    }
  } catch (err) {
    alert('Server response error.');
  }
});

// Delete user via AJAX
async function deleteUser(id, username) {
  if (!confirm(`Are you sure you want to delete account "${username}"?`)) return;

  const formData = new FormData();
  formData.append('id', id);

  try {
    const res = await fetch('../../backend/employee/user_actions.php?action=delete', {
      method: 'POST',
      body: formData
    });
    const result = await res.json();

    if (result.success) {
      alert(result.message);
      location.reload();
    } else {
      alert('Error: ' + result.message);
    }
  } catch (err) {
    alert('Deletion failed.');
  }
}
</script>
</body>
</html>