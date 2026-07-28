document.addEventListener('DOMContentLoaded', function () {

    // ===== FAQ ACCORDION =====
    document.querySelectorAll('.faq-question').forEach(function (q) {
        q.addEventListener('click', function () {
            var item = q.closest('.faq-item');
            var wasOpen = item.classList.contains('open');
            document.querySelectorAll('.faq-item').forEach(function (i) {
                i.classList.remove('open');
            });
            if (!wasOpen) {
                item.classList.add('open');
            }
        });
    });

    // ===== MOBILE MENU =====
    var toggle = document.querySelector('.mobile-menu-toggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            var nav = document.querySelector('.header-nav');
            nav.classList.toggle('mobile-open');
        });
    }

    // ===== SEARCH =====
    var searchInput = document.getElementById('search-input');
    var searchResults = document.getElementById('search-results');
    var lang = document.documentElement.getAttribute('lang') || 'nl';
    var indexFile = lang === 'en'
        ? '../assets/js/search-index-en.json'
        : 'assets/js/search-index-en.json';

    // Determine correct path based on current page depth
    var path = window.location.pathname;
    var depth = (path.match(/\//g) || []).length;
    // On root: docs/index.html → depth 1 (just /docs/)
    // On category: docs/nl/aan-de-slag/index.html → depth 3
    // On article: docs/nl/aan-de-slag/licentie.html → depth 3
    var basePath = '';
    if (path.indexOf('/nl/') !== -1 || path.indexOf('/en/') !== -1) {
        basePath = '../../';
    } else {
        basePath = '';
    }

    var indexUrl = basePath + 'assets/js/search-index-' + lang + '.json';
    var searchIndex = null;

    if (searchInput) {
        fetch(indexUrl)
            .then(function (r) { return r.json(); })
            .then(function (data) { searchIndex = data; })
            .catch(function () { searchIndex = []; });

        searchInput.addEventListener('input', function () {
            var query = searchInput.value.trim().toLowerCase();
            if (!query || query.length < 2 || !searchIndex) {
                searchResults.classList.remove('active');
                searchResults.innerHTML = '';
                return;
            }

            var matches = searchIndex.filter(function (item) {
                return item.title.toLowerCase().indexOf(query) !== -1 ||
                    item.description.toLowerCase().indexOf(query) !== -1 ||
                    (item.keywords && item.keywords.toLowerCase().indexOf(query) !== -1);
            }).slice(0, 8);

            if (matches.length === 0) {
                searchResults.innerHTML = '<div class="search-no-results">Geen resultaten gevonden</div>';
                searchResults.classList.add('active');
                return;
            }

            searchResults.innerHTML = matches.map(function (m) {
                return '<a href="' + basePath + m.url + '" class="search-result-item">' +
                    '<div class="title">' + m.title + '</div>' +
                    '<div class="category">' + m.category + '</div>' +
                    '</a>';
            }).join('');
            searchResults.classList.add('active');
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.search-wrapper')) {
                searchResults.classList.remove('active');
            }
        });
    }
});
