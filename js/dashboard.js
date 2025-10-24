// Marca link ativo no menu lateral
(function markActiveLink() {
  const current = location.pathname.replace(/\/$/, "");
  document.querySelectorAll(".sidebar-menu a").forEach((link) => {
    try {
      const path = new URL(link.href).pathname.replace(/\/$/, "");
      if (path === current) link.classList.add("active");
    } catch {}
  });
})();

// Toggle da sidebar para mobile + overlay
(function sidebarMobile() {
  const btn = document.getElementById("mobileMenuButton");
  const sidebar = document.getElementById("sidebar");
  const overlay = document.getElementById("sidebarOverlay");
  if (!btn || !sidebar || !overlay) return;

  const close = () => {
    sidebar.classList.remove("active");
    overlay.classList.remove("active");
  };
  const open = () => {
    sidebar.classList.add("active");
    overlay.classList.add("active");
  };
  btn.addEventListener("click", () => {
    const opened = sidebar.classList.contains("active");
    opened ? close() : open();
  });
  overlay.addEventListener("click", close);
})();

// Dropdown do usuário
(function userDropdown() {
  const btn = document.getElementById("userMenuButton");
  const dd = document.getElementById("userDropdown");
  if (!btn || !dd) return;
  btn.addEventListener("click", (e) => {
    e.stopPropagation();
    const show = dd.style.display === "block";
    dd.style.display = show ? "none" : "block";
    btn.setAttribute("aria-expanded", show ? "false" : "true");
  });
  document.addEventListener("click", (e) => {
    if (!dd.contains(e.target) && !btn.contains(e.target)) {
      dd.style.display = "none";
      btn.setAttribute("aria-expanded", "false");
    }
  });
})();

// Acessibilidade: Enter/Space ativa cards (anchors já são focáveis, mas mantemos para botões futuros)
document.querySelectorAll(".consultation-card").forEach((el) => {
  el.addEventListener("keydown", (e) => {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      el.click();
    }
  });
});
