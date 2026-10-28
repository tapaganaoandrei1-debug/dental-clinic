/* home.js - services list and logged-in shortcuts for index.html */
(function () {
  function make(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  async function loadServices() {
    if (USE_FAKE_DATA) return FAKE_SERVICES.filter((s) => s.is_active);
    // GET services.php -> { ok:true, services:[{ id, name, category, description, duration_minutes, price }] }
    // The backend should return active services only.
    const res = await fetch(`${API_URL}/services.php`, { credentials: "include", headers: { Accept: "application/json" } });
    const data = await res.json();
    return data.ok ? data.services : [];
  }

  function renderServices(services) {
    const grid = document.getElementById("service-grid");
    if (!services.length) {
      grid.replaceChildren(make("p", "empty-state", "Our services are being updated. Please check back soon."));
      return;
    }
    grid.replaceChildren(
      ...services.slice(0, 6).map((s) => {
        const card = make("article", "service-card");
        const meta = make("div", "service-meta");
        meta.append(make("span", "", `${s.duration_minutes} min`), make("strong", "", formatMoney(s.price)));
        card.append(
          make("p", "service-category", s.category || "Dental care"),
          make("h3", "", s.name),
          make("p", "service-desc", s.description || "Personalized care from our dental team."),
          meta
        );
        return card;
      })
    );
  }

  // Logged-in visitors get a shortcut instead of "Get started"
  const user = getSession();
  if (user) {
    const dest = homeFor(user.role);
    const actions = document.getElementById("hero-actions");
    const cta = document.createElement("a");
    cta.className = "btn btn-primary btn-lg";
    cta.href = dest;
    cta.textContent = "Go to your dashboard";
    actions.replaceChildren(cta);

    const closing = document.getElementById("closing-cta");
    closing.href = dest;
    closing.textContent = "Open your dashboard";
  }

  loadServices()
    .then(renderServices)
    .catch(() => {
      document
        .getElementById("service-grid")
        .replaceChildren(make("p", "empty-state", "We couldn't load the services right now. Please refresh the page."));
    });
})();
