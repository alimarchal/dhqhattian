/**
 * "Download PDF" for the dashboards. Runs in the browser (no server tools needed): every block marked
 * with data-pdf-block is drawn to an image and placed on landscape A4 pages, a block is never cut in
 * the middle. The libraries are loaded only when the button is pressed.
 */
export async function downloadDashboardPdf(button) {
    const original = button.textContent;
    button.disabled = true;
    button.textContent = 'Preparing PDF…';
    document.body.classList.add('pdf-capture'); // no animations / hover offsets while the page is drawn
    await new Promise((resolve) => setTimeout(resolve, 150));

    try {
        const [{ default: html2canvas }, { jsPDF }] = await Promise.all([import('html2canvas'), import('jspdf')]);

        const blocks = [...document.querySelectorAll('[data-pdf-block]')];
        const pdf = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
        const pageW = pdf.internal.pageSize.getWidth();
        const pageH = pdf.internal.pageSize.getHeight();
        const margin = 8;
        const usableW = pageW - margin * 2;
        const footer = 7;
        const title = button.dataset.title || document.title;
        const subtitle = button.dataset.subtitle || '';
        const stamp = new Date().toLocaleString();

        let y = margin;
        const decorate = (pageNumber) => {
            pdf.setFontSize(8);
            pdf.setTextColor(120);
            pdf.text(`${title}${subtitle ? ' · ' + subtitle : ''}`, margin, pageH - 3);
            pdf.text(`Generated ${stamp} · page ${pageNumber}`, pageW - margin, pageH - 3, { align: 'right' });
        };
        let page = 1;

        let done = 0;
        for (const block of blocks) {
            done += 1;
            button.textContent = `Preparing PDF ${done}/${blocks.length}…`;
            await new Promise((resolve) => setTimeout(resolve, 0)); // let the button text repaint
            // charts animate in; wait until the block has its final size
            const canvas = await html2canvas(block, { scale: 1.6, backgroundColor: '#ffffff', useCORS: true, logging: false, ignoreElements: (el) => el.dataset && el.dataset.html2canvasIgnore === 'true' });
            if (!canvas.width || !canvas.height) continue;

            let w = usableW;
            let h = (canvas.height * w) / canvas.width;
            const maxH = pageH - margin - footer;
            if (h > maxH) { // a very tall block: shrink it to one page
                h = maxH;
                w = (canvas.width * h) / canvas.height;
            }
            if (y + h > pageH - footer && y > margin) {
                decorate(page);
                pdf.addPage();
                page += 1;
                y = margin;
            }
            pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', margin + (usableW - w) / 2, y, w, h);
            y += h + 4;
        }
        decorate(page);

        const name = (title + ' ' + subtitle).trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'dashboard';
        pdf.save(`${name}.pdf`);
    } catch (error) {
        console.error('Dashboard PDF failed', error);
        alert('The PDF could not be created. You can still print the page (Ctrl+P) and choose "Save as PDF".');
    } finally {
        document.body.classList.remove('pdf-capture');
        button.disabled = false;
        button.textContent = original;
    }
}

window.downloadDashboardPdf = downloadDashboardPdf;
