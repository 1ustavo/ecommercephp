document.addEventListener("DOMContentLoaded", function () {
    const btnMenu = document.getElementById("btn-admin-menu");
    const sidebar = document.getElementById("admin-sidebar");
    const overlay = document.getElementById("admin-sidebar-overlay");

    if (!btnMenu || !sidebar || !overlay) return;

    function abrirMenu() {
        sidebar.classList.add("open");
        overlay.classList.add("show");
        btnMenu.classList.add("active");
        btnMenu.setAttribute("aria-expanded", "true");
        btnMenu.setAttribute("aria-label", "Fechar menu");
        document.body.style.overflow = "hidden";
    }

    function fecharMenu() {
        sidebar.classList.remove("open");
        overlay.classList.remove("show");
        btnMenu.classList.remove("active");
        btnMenu.setAttribute("aria-expanded", "false");
        btnMenu.setAttribute("aria-label", "Abrir menu");
        document.body.style.overflow = "";
    }

    btnMenu.addEventListener("click", function () {
        if (sidebar.classList.contains("open")) {
            fecharMenu();
        } else {
            abrirMenu();
        }
    });

    overlay.addEventListener("click", fecharMenu);

    // Fecha ao clicar em um link do menu
    sidebar.querySelectorAll("a").forEach(function (link) {
        link.addEventListener("click", fecharMenu);
    });

    // Fecha com ESC
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && sidebar.classList.contains("open")) {
            fecharMenu();
        }
    });
});