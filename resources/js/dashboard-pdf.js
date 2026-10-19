/**
 * "Download PDF" for the dashboards. Runs in the browser (no server tools needed): every block marked
 * with data-pdf-block is drawn to an image and placed on landscape A4 pages, a block is never cut in
 * the middle. The libraries are loaded only when the button is pressed.
 *
 * Each block is drawn from a small copy of the page (a hidden iframe holding only that block and the
 * page styles). Drawing straight from the live page makes html2canvas copy the whole document -
 * every chart - for every block, which is far too slow and can run the browser out of memory.
 */
async function createStage(width) {
    const frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = `position:fixed;left:-100000px;top:0;width:${width}px;height:200px;border:0;visibility:hidden`;
    document.body.appendChild(frame);

    const doc = frame.contentDocument;
    doc.open();
    doc.write('<!doctype html><html><head><meta charset="utf-8"></head><body></body></html>');
    doc.close();

    const loading = [];
    document.querySelectorAll('link[rel="stylesheet"], style').forEach((node) => {
        const copy = node.cloneNode(true);
        if (copy.tagName === 'LINK') {
            loading.push(new Promise((resolve) => { copy.onload = resolve; copy.onerror = resolve; }));
        }
        doc.head.appendChild(copy);
    });
    doc.body.className = `${document.body.className} pdf-capture`.trim(); // keeps the no-animation rules, so nothing is caught half faded in
    doc.body.style.cssText = 'margin:0;background:#fff';
    await Promise.all(loading);

    return { frame, doc, width };
}

async function captureBlock(html2canvas, stage, block) {
    const { frame, doc, width } = stage;
    doc.body.replaceChildren();

    const wrap = doc.createElement('div');
    wrap.style.cssText = `width:${width}px;background:#fff;padding:2px`;
    const clone = block.cloneNode(true);
    clone.querySelectorAll('[data-html2canvas-ignore="true"]').forEach((el) => el.remove());
    clone.querySelectorAll('[x-cloak]').forEach((el) => el.removeAttribute('x-cloak'));
    // html2canvas fails on a gradient painted into a zero-width box (an empty progress bar), so those are hidden
    const live = block.querySelectorAll('*');
    clone.querySelectorAll('*').forEach((el, i) => {
        const source = live[i];
        if (source && source.getBoundingClientRect().width < 1 && getComputedStyle(source).backgroundImage !== 'none') {
            el.style.display = 'none';
        }
    });
    // Charts are inline SVG; html2canvas is slow at walking their thousands of nodes, so each one is handed
    // to it as a plain image (the browser draws an SVG image natively and quickly).
    const pictures = [];
    clone.querySelectorAll('svg.apexcharts-svg').forEach((svg) => {
        const box = svg.getBoundingClientRect();
        const width = Number(svg.getAttribute('width')) || box.width;
        const height = Number(svg.getAttribute('height')) || box.height;
        if (!width || !height) return;
        if (!svg.getAttribute('xmlns')) svg.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        svg.style.fontFamily = 'Helvetica, Arial, sans-serif';
        const picture = doc.createElement('img');
        picture.style.cssText = `display:block;width:${width}px;height:${height}px`;
        // an SVG used as an image cannot inherit the page font, so name a sans-serif one explicitly
        const markup = new XMLSerializer().serializeToString(svg).replace(/font-family="inherit"/g, 'font-family="Helvetica, Arial, sans-serif"').replace(/font-family:\s*inherit/g, 'font-family: Helvetica, Arial, sans-serif');
        picture.src = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(markup)}`;
        pictures.push(picture.decode().catch(() => {}));
        svg.replaceWith(picture);
    });
    wrap.appendChild(clone);
    doc.body.appendChild(wrap);
    await Promise.all(pictures);

    frame.style.height = `${wrap.scrollHeight + 4}px`;
    return html2canvas(wrap, { scale: 1.5, backgroundColor: '#ffffff', logging: false, useCORS: true, width, windowWidth: width });
}

export async function downloadDashboardPdf(button) {
    const original = button.textContent;
    button.disabled = true;
    button.textContent = 'Preparing PDF…';
    document.body.classList.add('pdf-capture'); // no animations / hover offsets while the page is drawn
    await new Promise((resolve) => setTimeout(resolve, 150));

    try {
        const [{ default: html2canvas }, { jsPDF }] = await Promise.all([import('html2canvas'), import('jspdf')]);
        if (window.dashboardRenderAll) await window.dashboardRenderAll(); // draw the charts that were still waiting to scroll into view

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
        let page = 1;
        const decorate = (pageNumber) => {
            pdf.setFontSize(8);
            pdf.setTextColor(120);
            pdf.text(`${title}${subtitle ? ' · ' + subtitle : ''}`, margin, pageH - 3);
            pdf.text(`Generated ${stamp} · page ${pageNumber}`, pageW - margin, pageH - 3, { align: 'right' });
        };

        const stage = await createStage(Math.max(320, Math.round(blocks[0].getBoundingClientRect().width)));
        let done = 0;
        for (const block of blocks) {
            done += 1;
            button.textContent = `Preparing PDF ${done}/${blocks.length}…`;
            await new Promise((resolve) => requestAnimationFrame(resolve)); // let the button text repaint

            const canvas = await captureBlock(html2canvas, stage, block);
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
            pdf.addImage(canvas.toDataURL('image/jpeg', 0.9), 'JPEG', margin + (usableW - w) / 2, y, w, h, undefined, 'FAST');
            canvas.width = canvas.height = 0; // free the memory before the next block
            y += h + 4;
        }
        decorate(page);
        stage.frame.remove();

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

// test hook: draw one block (used to debug the export in automated browsers)
window.__captureBlock = async (block) => { const { default: html2canvas } = await import('html2canvas'); const stage = await createStage(Math.round(block.getBoundingClientRect().width)); try { return await captureBlock(html2canvas, stage, block); } finally { stage.frame.remove(); } };
