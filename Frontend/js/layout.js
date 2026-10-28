/* ==========================================================
   layout.js - builds the shared header and footer.
   Put <header id="site-header" data-active="home"></header> and
   <footer id="site-footer"></footer> on a page, then load:
   config.js, fake-data.js, auth.js, layout.js
   ========================================================== */
(function () {
  const PATIENT_NAV = [
    { key: "book", label: "Book Appointment", href: "book.html" },
    { key: "appointments", label: "My Appointments", href: "appointments.html" },
    { key: "queue", label: "Live Queue", href: "my-queue.html" },
    { key: "alerts", label: "Alerts", href: "alerts.html" },
  ];

  function make(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function link(label, href, className, current) {
    const a = make("a", className, label);
    a.href = href;
    if (current) a.setAttribute("aria-current", "page");
    return a;
  }

  function initials(name) {
    return (name || "")
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0].toUpperCase())
      .join("") || "DC";
  }

  function renderHeader() {
    const header = document.getElementById("site-header");
    if (!header) return;
    header.className = "site-header";
    const active = header.dataset.active || "";
    const user = getSession();

    const inner = make("div", "site-header-inner");
    const brand = make("a", "brand");
    brand.href = "index.html";
    brand.append(make("span", "brand-mark", "D"), make("span", "", CLINIC.name));

    const nav = make("nav", "site-nav");
    nav.setAttribute("aria-label", "Main");
    const actions = make("div", "site-actions");

    if (user && user.role === "patient") {
      PATIENT_NAV.forEach((item) => nav.append(link(item.label, item.href, "", item.key === active)));
    } else {
      nav.append(link("Home", "index.html", "", active === "home"), link("Services", "index.html#services", ""));
    }

    if (user) {
      const chip = make("span", "user-chip");
      chip.append(make("span", "avatar", initials(user.name)), make("span", "user-name", user.name));
      actions.append(chip);
      if (user.role !== "patient") actions.append(link("Open dashboard", homeFor(user.role), "btn btn-primary btn-sm"));
      const out = make("button", "btn btn-link btn-sm", "Log out");
      out.type = "button";
      out.addEventListener("click", logout);
      actions.append(out);
    } else {
      actions.append(link("Log in", "login.html", "nav-login", active === "login"), link("Sign up", "register.html", "btn btn-primary btn-sm"));
    }

    inner.append(brand, nav, actions);
    header.replaceChildren(inner);
  }

  function renderFooter() {
    const footer = document.getElementById("site-footer");
    if (!footer) return;
    footer.className = "site-footer";

    const inner = make("div", "page footer-inner");
    const about = make("div");
    about.append(make("p", "footer-brand", CLINIC.name), make("p", "footer-text", CLINIC.tagline));
    const contact = make("div");
    contact.append(
      make("p", "footer-text", CLINIC.address),
      make("p", "footer-text", `${CLINIC.phone} - ${CLINIC.email}`)
    );
    const legal = make("p", "footer-text footer-legal", `\u00A9 ${new Date().getFullYear()} ${CLINIC.name}`);

    inner.append(about, contact, legal);
    footer.replaceChildren(inner);
  }

  renderHeader();
  renderFooter();
})();
