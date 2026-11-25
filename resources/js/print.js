/*
 * Yazdırırken tabloları 15 satırlık A4 sayfalarına böler: her parça yeni sayfada başlar, sütun başlıkları
 * tekrar eder, toplam satırı yalnızca son parçada kalır. Yazdırma bitince sayfa eski haline döner.
 * Bölünmesi istenmeyen tabloya data-print-whole verilir.
 */
const ROWS_PER_PAGE = 15;
const restorers = [];

function paginate() {
    const title = document.querySelector('[data-report-title]')?.dataset.reportTitle ?? document.title.replace(/^.*\|\s*/, '');

    document.querySelectorAll('main table').forEach((table) => {
        if (table.closest('.no-print') || 'printWhole' in table.dataset) {
            return;
        }
        const body = table.tBodies[0];
        const rows = body ? [...body.rows] : [];
        if (rows.length <= ROWS_PER_PAGE) {
            return;
        }

        const pages = Math.ceil(rows.length / ROWS_PER_PAGE);
        const footer = table.tFoot;
        const added = [];
        let after = table;
        for (let page = 1; page < pages; page++) {
            const note = document.createElement('p');
            note.className = 'print-page-break print-continued';
            note.textContent = `${title} — devam (${page + 1} / ${pages})`;

            const part = table.cloneNode(false);
            if (table.tHead) {
                part.appendChild(table.tHead.cloneNode(true));
            }
            const partBody = document.createElement('tbody');
            rows.slice(page * ROWS_PER_PAGE, (page + 1) * ROWS_PER_PAGE).forEach((row) => partBody.appendChild(row));
            part.appendChild(partBody);

            after.after(note, part);
            after = part;
            added.push(note, part);
        }
        if (footer) {
            after.appendChild(footer);
        }

        restorers.push(() => {
            rows.forEach((row) => body.appendChild(row));
            if (footer) {
                table.appendChild(footer);
            }
            added.forEach((element) => element.remove());
        });
    });
}

function restore() {
    while (restorers.length) {
        restorers.pop()();
    }
}

const startPrint = () => {
    restore();
    paginate();
};

// Bazı tarayıcılar (ve PDF'e yazdırma) beforeprint olayını göndermez; baskı ortamına geçiş de dinlenir
window.addEventListener('beforeprint', startPrint);
window.addEventListener('afterprint', restore);
window.matchMedia('print').addEventListener('change', (event) => (event.matches ? startPrint() : restore()));
