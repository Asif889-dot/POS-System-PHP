<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Modern Split Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    body {
      background-color: #f4f6f9;
    }

    .login-container {
      max-width: 900px;
    }

    .brand-section {
      background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
      border-top-left-radius: 1rem;
      border-bottom-left-radius: 1rem;
    }

    @media (max-width: 767.98px) {
      .card-form-wrapper {
        border-radius: 1rem !important;
      }
    }
  </style>
</head>

<body>

  <div class="container min-vh-100 d-flex justify-content-center align-items-center py-5">
    <div class="login-container w-100">
      <div class="card border-0 shadow-lg card-form-wrapper overflow-hidden" style="border-radius: 1rem;">
        <div class="row g-0">

          <div class="col-md-5 d-none d-md-flex flex-column justify-content-between p-5 text-white brand-section">
            <div>
              <div class="d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-box-seam-fill fs-3"></i>
                <span class="fs-4 fw-bold">BrandApp</span>
              </div>
              <h2 class="fw-bold mb-3">Manage everything in one place.</h2>
              <p class="text-white-50">Access your dashboard, manage workflows, and collaborate seamlessly.</p>
            </div>
            <div class="small text-white-50">
              © 2026 Softzila Inc. All rights reserved.
            </div>
          </div>

          <div class="col-md-7 p-4 p-sm-5 bg-white d-flex flex-column justify-content-center">

            <div class="mb-4">
              <h4 class="fw-bold mb-1">Sign In</h4>
              <p class="text-muted small">Please enter your credentials to access your account.</p>
            </div>

            <form action="backend/auth/login.php" method="POST">
              <div class="mb-3">
                <label for="userEmail" class="form-label small fw-semibold">Email address</label>
                <input type="email" name="email" class="form-control form-control-lg fs-6" id="userEmail" placeholder="name@company.com" required>
              </div>

              <div class="mb-3">
                <label for="userPassword" class="form-label small fw-semibold">Password</label>
                <input type="password" name="password" class="form-control form-control-lg fs-6" id="userPassword" placeholder="••••••••" required>
              </div>

              <div class="form-check mb-4">
                <input type="checkbox" class="form-check-input" id="rememberSession">
                <label class="form-check-label text-muted small" for="rememberSession">Keep me signed in for 30 days</label>
              </div>

              <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold">Log In</button>
            </form>

          </div>

        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>