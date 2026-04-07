/* ═══════════════════════════════════════════════════════════════
   مخازن العناية – بطاقة تهنئة عيد الفطر
   ═══════════════════════════════════════════════════════════════

   📐 fontScale : حجم الخط نسبة من ارتفاع الصورة (0.01 – 0.20)
   📍 nameX     : الموضع الأفقي  0 = يمين ، 0.5 = وسط ، 1 = يسار
   📍 nameY     : الموضع الرأسي  0 = أعلى ، 0.5 = وسط ، 1 = أسفل
   🎨 fontColor : لون الخط مثل '#111111' أو '#FFFFFF'

   ═══════════════════════════════════════════════════════════════ */

const CARD_CONFIG = {
    imgId:     'card-img',
    fontScale: 0.055,
    nameX:     0.5,
    nameY:     0.61,
    fontColor: '#111111',
};

function generateCard() {
    const name = document.getElementById('name-input').value.trim();

    if (!name) {
        const input = document.getElementById('name-input');
        input.focus();
        input.style.borderColor = '#c0392b';
        setTimeout(() => (input.style.borderColor = ''), 2000);
        return;
    }

    const btn = document.querySelector('#step1 .btn-primary');
    btn.disabled    = true;
    btn.textContent = 'جاري الإنشاء…';

    const img    = document.getElementById(CARD_CONFIG.imgId);
    const canvas = document.getElementById('result-canvas');
    const ctx    = canvas.getContext('2d');

    const done = () => {
        btn.disabled    = false;
        btn.innerHTML   = `<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg> إنشاء البطاقة`;
    };

    const render = () => {
        canvas.width  = img.naturalWidth;
        canvas.height = img.naturalHeight;

        if (!canvas.width) { done(); return; }

        ctx.drawImage(img, 0, 0);

        const baseFontSize = Math.floor(canvas.height * CARD_CONFIG.fontScale);
        const fontSpec     = `700 ${baseFontSize}px PNUFont, Cairo, sans-serif`;

        const drawText = () => {
            /* auto-shrink font to fit 70% of card width */
            const maxW = canvas.width * 0.70;
            let fs = baseFontSize;
            ctx.font         = `700 ${fs}px PNUFont, Cairo, sans-serif`;
            ctx.textAlign    = 'center';
            ctx.textBaseline = 'middle';
            while (fs > 12 && ctx.measureText(name).width > maxW) {
                fs -= 2;
                ctx.font = `700 ${fs}px PNUFont, Cairo, sans-serif`;
            }

            ctx.fillStyle = CARD_CONFIG.fontColor;
            ctx.fillText(name, canvas.width * CARD_CONFIG.nameX, canvas.height * CARD_CONFIG.nameY);

            done();

            document.getElementById('step1').classList.remove('active');
            document.getElementById('step2').classList.add('active');
        };

        (document.fonts ? document.fonts.load(fontSpec) : Promise.resolve())
            .then(drawText).catch(drawText);
    };

    if (img.complete && img.naturalWidth > 0) {
        render();
    } else {
        img.onload  = render;
        img.onerror = () => {
            done();
            alert('تعذّر تحميل صورة البطاقة (cardeid.webp). تأكد من وجود الملف.');
        };
    }
}

function downloadCard() {
    const canvas = document.getElementById('result-canvas');
    const name   = document.getElementById('name-input').value.trim();

    try {
        canvas.toBlob(blob => {
            if (!blob) { alert('تعذّر تصدير البطاقة، حاول مرة أخرى.'); return; }
            const link    = document.createElement('a');
            link.href     = URL.createObjectURL(blob);
            link.download = `تهنئة_عيد_الفطر_${name || 'مخازن'}.jpg`;
            link.click();
            URL.revokeObjectURL(link.href);
        }, 'image/jpeg', 0.95);
    } catch (e) {
        const url = canvas.toDataURL('image/jpeg', 0.95);
        const win = window.open();
        win.document.write(`<img src="${url}" style="max-width:100%">`);
    }
}

function goBack() {
    document.getElementById('step2').classList.remove('active');
    document.getElementById('step1').classList.add('active');
}

document.addEventListener('DOMContentLoaded', () => {
    window.scrollTo(0, 0);
    document.getElementById('name-input').addEventListener('keypress', e => {
        if (e.key === 'Enter') generateCard();
    });
});
