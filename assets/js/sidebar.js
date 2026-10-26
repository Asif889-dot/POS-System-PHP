document.addEventListener("DOMContentLoaded", function () {
  const currentPath = window.location.pathname.split("/").pop() || "index.php";
  const sidebarMenu = document.getElementById("sidebar-menu");

  if (!sidebarMenu) return;

  // Query all navigation links inside sidebar
  const navLinks = sidebarMenu.querySelectorAll(".nav-link");

  navLinks.forEach((link) => {
    const href = link.getAttribute("href");

    if (href && href === currentPath) {
      // Highlight the active link
      link.classList.add("active");
      link.classList.remove("text-white-50");

      // Check if this link is inside a collapsed sub-menu
      const parentCollapse = link.closest(".collapse");
      if (parentCollapse) {
        // Expand the collapse element via Bootstrap instance
        const bsCollapse = new bootstrap.Collapse(parentCollapse, {
          toggle: true
        });

        // Set aria-expanded="true" on parent dropdown button
        const parentToggle = document.querySelector(`[href="#${parentCollapse.id}"]`);
        if (parentToggle) {
          parentToggle.classList.add("active");
          parentToggle.setAttribute("aria-expanded", "true");
        }
      }
    }
  });
});