// Key Visual - Toggle between Grid View and Detail View
function showKvDetail(cardEl) {
    var gridView = document.getElementById('kvGridView');
    var gridHeader = document.getElementById('kvGridHeader');
    var gridSearch = document.getElementById('kvGridSearch');
    var detailView = document.getElementById('kvDetailView');
    if (!gridView || !detailView) return;

    gridView.classList.add('d-none');
    gridHeader.classList.add('d-none');
    gridSearch.classList.add('d-none');
    detailView.classList.remove('d-none');
}

function hideKvDetail() {
    var gridView = document.getElementById('kvGridView');
    var gridHeader = document.getElementById('kvGridHeader');
    var gridSearch = document.getElementById('kvGridSearch');
    var detailView = document.getElementById('kvDetailView');
    if (!gridView || !detailView) return;

    detailView.classList.add('d-none');
    gridView.classList.remove('d-none');
    gridHeader.classList.remove('d-none');
    gridSearch.classList.remove('d-none');
}
