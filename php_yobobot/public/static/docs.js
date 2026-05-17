(function () {
  const input = document.getElementById('docsSearch');
  const meta = document.getElementById('docsSearchMeta');
  const items = Array.from(document.querySelectorAll('[data-doc-search]'));
  const sections = Array.from(document.querySelectorAll('.docs-section'));

  if (!input || !items.length) {
    return;
  }

  const defaultMeta =
    'Browse by section or search terms like calendar, onboarding, availability, widget, or API.';

  function normalize(value) {
    return String(value || '').trim().toLowerCase();
  }

  function sectionHasVisibleItems(section) {
    const sectionItems = Array.from(section.querySelectorAll('[data-doc-search]'));
    if (!sectionItems.length) {
      return true;
    }
    return sectionItems.some((item) => !item.classList.contains('docs-hidden'));
  }

  function updateSections(query) {
    sections.forEach((section) => {
      if (!query) {
        section.classList.remove('docs-hidden');
        return;
      }
      section.classList.toggle('docs-hidden', !sectionHasVisibleItems(section));
    });
  }

  function applySearch() {
    const query = normalize(input.value);
    let visibleCount = 0;

    items.forEach((item) => {
      const searchBlob = normalize(item.getAttribute('data-doc-search')) + ' ' + normalize(item.textContent);
      const matches = !query || searchBlob.includes(query);
      item.classList.toggle('docs-hidden', !matches);

      if (item.tagName === 'DETAILS') {
        if (matches && query) {
          item.setAttribute('open', 'open');
        } else if (!query) {
          item.removeAttribute('open');
        }
      }

      if (matches) {
        visibleCount += 1;
      }
    });

    updateSections(query);

    if (!query) {
      meta.textContent = defaultMeta;
      return;
    }

    meta.textContent = visibleCount
      ? `Showing ${visibleCount} matching result${visibleCount === 1 ? '' : 's'} for "${query}".`
      : `No results found for "${query}". Try terms like onboarding, booking, dashboard, integrations, or API.`;
  }

  input.addEventListener('input', applySearch);
})();
