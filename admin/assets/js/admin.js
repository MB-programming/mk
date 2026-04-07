/* ============================================================
   Makhazen Alenayah - Admin Dashboard JS
============================================================ */

"use strict";

// ============================================================
// STATE
// ============================================================
let quillInstance = null; // Quill editor instance for category body

const state = {
  currentTab: 'branches',
  editId: null,
  editTable: null,
  deleteId: null,
  deleteTable: null,
  data: {
    branches: [],
    brands: [],
    social: [],
    contact: [],
    categories: [],
  },
};

// ============================================================
// API HELPERS
// ============================================================
const API_BASE = '../api/';

async function apiGet(table) {
  const res  = await fetch(`${API_BASE}admin_crud.php?table=${table}`);
  const data = await res.json();
  if (!data.success) throw new Error(data.message || 'فشل التحميل');
  return data.data;
}

async function apiPost(table, body) {
  const res  = await fetch(`${API_BASE}admin_crud.php?table=${table}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return res.json();
}

async function apiPut(table, id, body) {
  const res  = await fetch(`${API_BASE}admin_crud.php?table=${table}&id=${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return res.json();
}

async function apiDelete(table, id) {
  const res = await fetch(`${API_BASE}admin_crud.php?table=${table}&id=${id}`, {
    method: 'DELETE',
  });
  return res.json();
}

async function apiToggle(table, id) {
  const res = await fetch(`${API_BASE}admin_crud.php?table=${table}&id=${id}`, {
    method: 'PATCH',
  });
  return res.json();
}

// ============================================================
// TOAST
// ============================================================
function showToast(msg, type = 'success') {
  const toast = document.getElementById('toast');
  toast.textContent = msg;
  toast.className   = `toast show ${type}`;
  clearTimeout(toast._timer);
  toast._timer = setTimeout(() => toast.classList.remove('show'), 3000);
}

// ============================================================
// TABS
// ============================================================
function initTabs() {
  document.querySelectorAll('.nav-item').forEach(btn => {
    btn.addEventListener('click', () => {
      const tab = btn.dataset.tab;
      switchTab(tab);
      // Close sidebar on mobile
      closeSidebar();
    });
  });
}

function switchTab(tab) {
  state.currentTab = tab;

  document.querySelectorAll('.nav-item').forEach(b => {
    b.classList.toggle('active', b.dataset.tab === tab);
  });
  document.querySelectorAll('.tab-content').forEach(c => {
    c.classList.toggle('active', c.id === `tab-${tab}`);
  });

  const titles = {
    branches:    'الفروع',
    brands:      'البراندات',
    social:      'التواصل الاجتماعي',
    contact:     'معلومات التواصل',
    categories:  'الأقسام',
    performance: 'إعدادات الأداء',
    tracking:    'كودات التتبع',
  };
  document.getElementById('topbar-title').textContent = titles[tab] || tab;
}

// ============================================================
// RENDER FUNCTIONS
// ============================================================

// --- BRANCHES ---
function renderBranches(rows) {
  const tbody = document.getElementById('branches-tbody');
  tbody.innerHTML = '';

  rows.forEach((row, i) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${i + 1}</td>
      <td><strong style="color:#fff">${row.name_ar}</strong></td>
      <td>${row.city_ar || '-'}</td>
      <td dir="ltr">${row.phone || '-'}</td>
      <td>${statusBadge(row.is_active)}</td>
      <td>
        <div class="actions">
          <button class="btn-icon" title="تعديل" onclick="editRecord('branches', ${row.id})"><i class="fas fa-edit"></i></button>
          <button class="btn-icon" title="تفعيل/تعطيل" onclick="toggleRecord('branches', ${row.id})"><i class="fas fa-toggle-on"></i></button>
          <button class="btn-icon del" title="حذف" onclick="confirmDelete('branches', ${row.id})"><i class="fas fa-trash"></i></button>
        </div>
      </td>`;
    tbody.appendChild(tr);
  });

  document.getElementById('branches-count').textContent = `(${rows.length} فرع)`;
  document.getElementById('badge-branches').textContent = rows.length;
}

// --- BRANDS ---
function renderBrands(rows) {
  const tbody = document.getElementById('brands-tbody');
  tbody.innerHTML = '';

  rows.forEach((row, i) => {
    const logoHtml = row.logo_url
      ? `<img src="../${row.logo_url}" class="brand-thumb" alt="${row.name_en}" />`
      : `<div class="brand-placeholder-icon"><i class="fas fa-tag"></i></div>`;

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${i + 1}</td>
      <td>${logoHtml}</td>
      <td><strong style="color:#fff">${row.name_en}</strong></td>
      <td>${row.name_ar || '-'}</td>
      <td>${statusBadge(row.is_active)}</td>
      <td>
        <div class="actions">
          <button class="btn-icon" title="تعديل" onclick="editRecord('brands', ${row.id})"><i class="fas fa-edit"></i></button>
          <button class="btn-icon" title="تفعيل/تعطيل" onclick="toggleRecord('brands', ${row.id})"><i class="fas fa-toggle-on"></i></button>
          <button class="btn-icon del" title="حذف" onclick="confirmDelete('brands', ${row.id})"><i class="fas fa-trash"></i></button>
        </div>
      </td>`;
    tbody.appendChild(tr);
  });

  document.getElementById('brands-count').textContent = `(${rows.length} براند)`;
  document.getElementById('badge-brands').textContent = rows.length;
}

// --- SOCIAL ---
function renderSocial(rows) {
  const tbody = document.getElementById('social-tbody');
  tbody.innerHTML = '';

  rows.forEach((row, i) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${i + 1}</td>
      <td><strong style="color:#fff">${row.platform}</strong></td>
      <td>${row.platform_ar || '-'}</td>
      <td>${row.username || '-'}</td>
      <td><a href="${row.url}" target="_blank" style="color:#FFCF06;font-size:11px">${truncate(row.url, 30)}</a></td>
      <td>${statusBadge(row.is_active)}</td>
      <td>
        <div class="actions">
          <button class="btn-icon" title="تعديل" onclick="editRecord('social_media', ${row.id})"><i class="fas fa-edit"></i></button>
          <button class="btn-icon" title="تفعيل/تعطيل" onclick="toggleRecord('social_media', ${row.id})"><i class="fas fa-toggle-on"></i></button>
          <button class="btn-icon del" title="حذف" onclick="confirmDelete('social_media', ${row.id})"><i class="fas fa-trash"></i></button>
        </div>
      </td>`;
    tbody.appendChild(tr);
  });

  document.getElementById('social-count').textContent = `(${rows.length} منصة)`;
  document.getElementById('badge-social').textContent = rows.length;
}

// --- CONTACT ---
function renderContact(rows) {
  const tbody = document.getElementById('contact-tbody');
  tbody.innerHTML = '';

  rows.forEach((row, i) => {
    const typeLabels = { phone: 'هاتف', whatsapp: 'واتساب', email: 'بريد إلكتروني' };
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${i + 1}</td>
      <td>${typeLabels[row.type] || row.type}</td>
      <td dir="ltr"><strong style="color:#fff">${row.value}</strong></td>
      <td>${row.label_ar || '-'}</td>
      <td>${statusBadge(row.is_active)}</td>
      <td>
        <div class="actions">
          <button class="btn-icon" title="تعديل" onclick="editRecord('contact_info', ${row.id})"><i class="fas fa-edit"></i></button>
          <button class="btn-icon del" title="حذف" onclick="confirmDelete('contact_info', ${row.id})"><i class="fas fa-trash"></i></button>
        </div>
      </td>`;
    tbody.appendChild(tr);
  });
}

// --- CATEGORIES ---
function renderCategories(rows) {
  const tbody = document.getElementById('categories-tbody');
  tbody.innerHTML = '';

  rows.forEach((row, i) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${i + 1}</td>
      <td><i class="fas ${row.icon || 'fa-star'}" style="font-size:20px;color:#FFCF06"></i></td>
      <td><strong style="color:#fff">${row.name_ar}</strong></td>
      <td dir="ltr" style="font-size:12px;color:#aaa">${row.slug}</td>
      <td>${statusBadge(row.is_active)}</td>
      <td>
        <div class="actions">
          <button class="btn-icon" title="معاينة" onclick="window.open('../category.php?slug=${row.slug}','_blank')"><i class="fas fa-eye"></i></button>
          <button class="btn-icon" title="تعديل" onclick="editRecord('categories', ${row.id})"><i class="fas fa-edit"></i></button>
          <button class="btn-icon" title="تفعيل/تعطيل" onclick="toggleRecord('categories', ${row.id})"><i class="fas fa-toggle-on"></i></button>
          <button class="btn-icon del" title="حذف" onclick="confirmDelete('categories', ${row.id})"><i class="fas fa-trash"></i></button>
        </div>
      </td>`;
    tbody.appendChild(tr);
  });

  document.getElementById('categories-count').textContent = `(${rows.length} قسم)`;
  document.getElementById('badge-categories').textContent = rows.length;
}

// ============================================================
// CATEGORY EDITOR  (Quill ↔ HTML toggle)
// ============================================================
function initCategoryEditor(bodyHTML = '') {
  const el = document.getElementById('quill-editor');
  if (!el) return;

  quillInstance = new Quill('#quill-editor', {
    theme: 'snow',
    direction: 'rtl',
    modules: {
      toolbar: [
        [{ header: [2, 3, false] }],
        ['bold', 'italic', 'underline'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['link'],
        ['clean'],
      ],
    },
  });

  // Set initial content
  if (bodyHTML) {
    quillInstance.root.innerHTML = bodyHTML;
  }
}

function switchEditorMode(mode) {
  const quillWrap = document.getElementById('quill-wrap');
  const textarea  = document.getElementById('f-body');
  const btnW      = document.getElementById('btn-wysiwyg');
  const btnH      = document.getElementById('btn-html');
  if (!quillWrap || !textarea) return;

  if (mode === 'html') {
    // Sync Quill → textarea
    if (quillInstance) textarea.value = quillInstance.root.innerHTML;
    quillWrap.style.display  = 'none';
    textarea.style.display   = '';
    btnW.classList.remove('active');
    btnH.classList.add('active');
  } else {
    // Sync textarea → Quill
    if (!quillInstance) initCategoryEditor(textarea.value);
    else quillInstance.root.innerHTML = textarea.value;
    textarea.style.display   = 'none';
    quillWrap.style.display  = '';
    btnH.classList.remove('active');
    btnW.classList.add('active');
  }
}

// ============================================================
// SLIDER SETTINGS
// ============================================================
async function loadSliderSettings() {
  try {
    const res  = await fetch('../api/settings.php?admin=1');
    const data = await res.json();
    if (!data.success) return;
    data.data.forEach(row => {
      const el = document.getElementById(row.key === 'slider_speed' ? 'slider_speed_sec' : row.key);
      if (!el) return;
      el.value = row.key === 'slider_speed'
        ? (parseFloat(row.value) / 1000).toFixed(1)
        : row.value;
    });
  } catch (_) {}
}

async function saveSliderSettings() {
  const perView  = parseInt(document.getElementById('slider_per_view')?.value  || 5);
  const autoplay = document.getElementById('slider_autoplay')?.value  || '1';
  const speedSec = parseFloat(document.getElementById('slider_speed_sec')?.value || 3);

  try {
    const res  = await fetch('../api/settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        slider_per_view: perView,
        slider_autoplay: autoplay,
        slider_speed:    Math.round(speedSec * 1000),
      }),
    });
    const data = await res.json();
    showToast(data.success ? 'تم حفظ إعدادات السلايدر' : (data.message || 'حدث خطأ'), data.success ? 'success' : 'error');
  } catch {
    showToast('فشل الاتصال بالخادم', 'error');
  }
}

// ============================================================
// PERFORMANCE SETTINGS + CACHE MANAGER
// ============================================================

// ── Format seconds to human-readable ─────────────
function fmtSec(sec) {
  sec = Math.max(0, Math.round(+sec));
  if (sec < 60)   return sec + 'ث';
  if (sec < 3600) return Math.floor(sec / 60) + ' دقيقة';
  return Math.floor(sec / 3600) + ' ساعة';
}

// ── Cache status panel ────────────────────────────
async function loadCacheStatus() {
  try {
    const res  = await fetch('../api/clear_cache.php?status=1');
    const data = await res.json();
    if (!data.success) return;

    const s     = data.cache;
    const badge = document.getElementById('cache-live-badge');
    const btext = document.getElementById('cache-badge-text');

    if (document.getElementById('cache-generated-at'))
      document.getElementById('cache-generated-at').textContent = s.exists ? s.generated_at : '—';
    if (document.getElementById('cache-age'))
      document.getElementById('cache-age').textContent = s.exists ? fmtSec(s.age_seconds) : '—';
    if (document.getElementById('cache-remaining'))
      document.getElementById('cache-remaining').textContent = s.exists ? fmtSec(s.remaining) : '—';
    if (document.getElementById('cache-ttl-display'))
      document.getElementById('cache-ttl-display').textContent = s.exists ? fmtSec(s.ttl) : '—';

    if (!badge || !btext) return;
    if (!s.exists) {
      badge.className = 'cache-live-badge empty';
      btext.textContent = 'لا يوجد كاش — سيُنشأ أول طلب';
    } else if (s.fresh) {
      badge.className = 'cache-live-badge';
      btext.textContent = 'نشط — عمر ' + fmtSec(s.age_seconds);
    } else {
      badge.className = 'cache-live-badge stale';
      btext.textContent = 'منتهي — سيُجدَّد أول طلب';
    }
  } catch (_) {}
}

async function loadPerfSettings() {
  try {
    const res  = await fetch('../api/settings.php?admin=1');
    const data = await res.json();
    if (!data.success) return;

    const perfKeys = ['perf_animations', 'perf_cache_api', 'perf_minify_html', 'cache_ttl'];
    data.data.forEach(row => {
      if (!perfKeys.includes(row.key)) return;
      const el = document.getElementById(row.key);
      if (el) el.value = row.value || (row.key === 'cache_ttl' ? '300' : '1');
    });

    // OPcache status indicator
    const badge = document.getElementById('opcache-status');
    if (badge) {
      badge.innerHTML = `<span class="status-badge status-active"><i class="fas fa-check-circle"></i> مفعّل — يُمسح عند الضغط على "مسح الكاش"</span>`;
    }

    // Also refresh cache status card
    loadCacheStatus();
  } catch (_) {}
}

async function savePerfSettings() {
  const body = {
    perf_animations:  document.getElementById('perf_animations')?.value  || '1',
    perf_cache_api:   document.getElementById('perf_cache_api')?.value   || '1',
    perf_minify_html: document.getElementById('perf_minify_html')?.value || '1',
    cache_ttl:        document.getElementById('cache_ttl')?.value        || '300',
  };

  try {
    const res  = await fetch('../api/settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const data = await res.json();
    showToast(data.success ? 'تم حفظ الإعدادات ✓' : (data.message || 'حدث خطأ'), data.success);
    if (data.success) loadCacheStatus();
  } catch {
    showToast('فشل الاتصال بالخادم', false);
  }
}

// ============================================================
// CLEAR SITE CACHE
// ============================================================
async function clearSiteCache() {
  const btn  = document.getElementById('clear-cache-btn');
  const icon = btn?.querySelector('i');
  if (icon) icon.className = 'fas fa-spinner fa-spin';
  if (btn)  btn.disabled = true;

  try {
    const res  = await fetch('../api/clear_cache.php', { method: 'POST' });
    const data = await res.json();
    showToast(data.message || 'تم مسح الكاش', data.success);
    // Refresh cache status badge after clearing
    setTimeout(loadCacheStatus, 400);
  } catch {
    showToast('فشل الاتصال بالخادم', false);
  }

  if (icon) icon.className = 'fas fa-broom';
  if (btn)  btn.disabled = false;
}

// ============================================================
// LOAD ALL DATA
// ============================================================
async function loadAll() {
  try {
    const [branches, brands, social, contact, categories] = await Promise.all([
      apiGet('branches'),
      apiGet('brands'),
      apiGet('social_media'),
      apiGet('contact_info'),
      apiGet('categories'),
    ]);

    state.data.branches   = branches;
    state.data.brands     = brands;
    state.data.social     = social;
    state.data.contact    = contact;
    state.data.categories = categories;

    renderBranches(branches);
    renderBrands(brands);
    renderSocial(social);
    renderContact(contact);
    renderCategories(categories);
  } catch (err) {
    showToast('فشل تحميل البيانات: ' + err.message, 'error');
  }
}

// ============================================================
// MODAL FORMS
// ============================================================
const FORMS = {
  branch: (data = {}) => `
    <div class="form-row">
      <div class="modal-form-group">
        <label>الاسم (عربي) *</label>
        <input type="text" class="form-input" id="f-name_ar" value="${data.name_ar || ''}" placeholder="اسم الفرع" />
      </div>
      <div class="modal-form-group">
        <label>الاسم (إنجليزي)</label>
        <input type="text" class="form-input" id="f-name_en" value="${data.name_en || ''}" placeholder="Branch Name" />
      </div>
    </div>
    <div class="form-row">
      <div class="modal-form-group">
        <label>المدينة (عربي) *</label>
        <input type="text" class="form-input" id="f-city_ar" value="${data.city_ar || ''}" placeholder="الرياض" />
      </div>
      <div class="modal-form-group">
        <label>المدينة (إنجليزي)</label>
        <input type="text" class="form-input" id="f-city_en" value="${data.city_en || ''}" placeholder="Riyadh" />
      </div>
    </div>
    <div class="modal-form-group">
      <label>العنوان (عربي)</label>
      <textarea class="form-input" id="f-address_ar" placeholder="العنوان بالتفصيل">${data.address_ar || ''}</textarea>
    </div>
    <div class="form-row">
      <div class="modal-form-group">
        <label>رقم الهاتف</label>
        <input type="text" class="form-input" id="f-phone" value="${data.phone || ''}" placeholder="0112345678" dir="ltr" />
      </div>
      <div class="modal-form-group">
        <label>رابط خرائط</label>
        <input type="url" class="form-input" id="f-map_url" value="${data.map_url || ''}" placeholder="https://maps.google.com/..." dir="ltr" />
      </div>
    </div>
    <div class="form-row">
      <div class="modal-form-group">
        <label>الترتيب</label>
        <input type="number" class="form-input" id="f-sort_order" value="${data.sort_order || 0}" min="0" />
      </div>
      <div class="modal-form-group">
        <label>الحالة</label>
        <select class="form-input" id="f-is_active">
          <option value="1" ${data.is_active != 0 ? 'selected' : ''}>نشط</option>
          <option value="0" ${data.is_active == 0 ? 'selected' : ''}>معطل</option>
        </select>
      </div>
    </div>`,

  brand: (data = {}) => `
    <div class="form-row">
      <div class="modal-form-group">
        <label>الاسم (إنجليزي) *</label>
        <input type="text" class="form-input" id="f-name_en" value="${data.name_en || ''}" placeholder="Brand Name" />
      </div>
      <div class="modal-form-group">
        <label>الاسم (عربي)</label>
        <input type="text" class="form-input" id="f-name_ar" value="${data.name_ar || ''}" placeholder="اسم البراند" />
      </div>
    </div>
    <div class="modal-form-group">
      <label>رابط الموقع</label>
      <input type="url" class="form-input" id="f-website_url" value="${data.website_url || ''}" placeholder="https://brand.com" dir="ltr" />
    </div>
    <div class="modal-form-group">
      <label>شعار البراند</label>
      <div id="logo-zone">
        ${data.logo_url
          ? `<div class="logo-current-wrap">
               <div class="logo-current-preview">
                 <img src="../${data.logo_url}" alt="الشعار الحالي" id="logo-current-img" />
               </div>
               <div class="logo-current-actions">
                 <button type="button" class="btn-change-logo" onclick="document.getElementById('logo-file').click()">
                   <i class="fas fa-sync-alt"></i> تغيير الشعار
                 </button>
                 <button type="button" class="btn-remove-logo" onclick="removeLogo()">
                   <i class="fas fa-trash"></i> حذف الشعار
                 </button>
               </div>
             </div>`
          : `<div class="upload-area" id="upload-area-btn" onclick="document.getElementById('logo-file').click()">
               <i class="fas fa-cloud-upload-alt"></i>
               <p>اضغط لرفع الشعار</p>
               <small style="color:#555">JPG · PNG · WebP · SVG (بحد أقصى 2MB)</small>
             </div>`
        }
        <div class="upload-preview" id="upload-preview" style="${data.logo_url ? 'display:none' : ''}"></div>
      </div>
      <input type="file" id="logo-file" accept="image/*" style="display:none" onchange="previewAndUpload(this)" />
      <input type="hidden" id="f-logo_url" value="${data.logo_url || ''}" />
    </div>
    <div class="form-row">
      <div class="modal-form-group">
        <label>الترتيب</label>
        <input type="number" class="form-input" id="f-sort_order" value="${data.sort_order || 0}" min="0" />
      </div>
      <div class="modal-form-group">
        <label>الحالة</label>
        <select class="form-input" id="f-is_active">
          <option value="1" ${data.is_active != 0 ? 'selected' : ''}>نشط</option>
          <option value="0" ${data.is_active == 0 ? 'selected' : ''}>معطل</option>
        </select>
      </div>
    </div>`,

  social: (data = {}) => `
    <div class="form-row">
      <div class="modal-form-group">
        <label>المنصة (إنجليزي) *</label>
        <input type="text" class="form-input" id="f-platform" value="${data.platform || ''}" placeholder="Instagram" />
      </div>
      <div class="modal-form-group">
        <label>المنصة (عربي)</label>
        <input type="text" class="form-input" id="f-platform_ar" value="${data.platform_ar || ''}" placeholder="انستقرام" />
      </div>
    </div>
    <div class="modal-form-group">
      <label>الرابط *</label>
      <input type="url" class="form-input" id="f-url" value="${data.url || ''}" placeholder="https://instagram.com/..." dir="ltr" />
    </div>
    <div class="form-row">
      <div class="modal-form-group">
        <label>اسم المستخدم</label>
        <input type="text" class="form-input" id="f-username" value="${data.username || ''}" placeholder="@username" dir="ltr" />
      </div>
      <div class="modal-form-group">
        <label>أيقونة Font Awesome</label>
        <input type="text" class="form-input" id="f-icon" value="${data.icon || ''}" placeholder="fa-instagram" dir="ltr" />
      </div>
    </div>
    <div class="form-row">
      <div class="modal-form-group">
        <label>لون المنصة</label>
        <input type="color" class="form-input" id="f-color" value="${data.color || '#ffffff'}" style="height:42px;padding:4px" />
      </div>
      <div class="modal-form-group">
        <label>الترتيب</label>
        <input type="number" class="form-input" id="f-sort_order" value="${data.sort_order || 0}" min="0" />
      </div>
    </div>
    <div class="modal-form-group">
      <label>الحالة</label>
      <select class="form-input" id="f-is_active">
        <option value="1" ${data.is_active != 0 ? 'selected' : ''}>نشط</option>
        <option value="0" ${data.is_active == 0 ? 'selected' : ''}>معطل</option>
      </select>
    </div>`,

  category: (data = {}) => `
    <div class="modal-form-group">
      <label>اسم القسم (عربي) *</label>
      <input type="text" class="form-input" id="f-name_ar" value="${data.name_ar || ''}" placeholder="العناية بالبشرة" />
    </div>
    <div class="modal-form-group">
      <label>أيقونة Font Awesome</label>
      <input type="text" class="form-input" id="f-icon" value="${data.icon || 'fa-star'}" placeholder="fa-spa" dir="ltr" />
      <small style="color:#888;font-size:11px;margin-top:4px;display:block">أمثلة: fa-spa · fa-paint-brush · fa-leaf · fa-cut · fa-baby · fa-apple-alt · fa-heartbeat · fa-spray-can</small>
    </div>
    <div class="modal-form-group">
      <label>وصف مختصر</label>
      <input type="text" class="form-input" id="f-description" value="${data.description || ''}" placeholder="جملة تعريفية قصيرة تظهر في الصفحة الداخلية" />
    </div>
    <div class="modal-form-group">
      <label>المحتوى الكامل</label>
      <div class="editor-toggle-bar">
        <button type="button" class="editor-mode-btn active" id="btn-wysiwyg" onclick="switchEditorMode('wysiwyg')">
          <i class="fas fa-font"></i> مرئي
        </button>
        <button type="button" class="editor-mode-btn" id="btn-html" onclick="switchEditorMode('html')">
          <i class="fas fa-code"></i> HTML
        </button>
      </div>
      <div class="quill-wrap" id="quill-wrap">
        <div id="quill-editor"></div>
      </div>
      <textarea class="form-input" id="f-body" rows="10"
        style="display:none;font-family:monospace;font-size:13px;direction:ltr;text-align:left"
        placeholder="أدخل كود HTML هنا...">${(data.body || '').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</textarea>
    </div>
    <div class="modal-form-group" style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.07)">
      <label style="font-size:11px;text-transform:uppercase;letter-spacing:1.2px;color:#888;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:6px">
        <i class="fas fa-search" style="color:#FFCF06"></i> إعدادات SEO
      </label>
    </div>
    <div class="form-row">
      <div class="modal-form-group">
        <label>Slug (رابط القسم) *</label>
        <input type="text" class="form-input" id="f-slug" value="${data.slug || ''}" placeholder="skincare" dir="ltr" />
      </div>
      <div class="modal-form-group">
        <label>SEO Title <small style="color:#888;font-weight:400">(max 60)</small></label>
        <input type="text" class="form-input" id="f-seo_title" value="${data.seo_title || ''}"
               placeholder="العناية بالبشرة | مخازن العناية" maxlength="70" dir="auto" />
      </div>
    </div>
    <div class="modal-form-group">
      <label>SEO Description <small style="color:#888;font-weight:400">(وصف محركات البحث — max 160)</small></label>
      <textarea class="form-input" id="f-seo_description" rows="3"
                placeholder="وصف قصير يظهر في نتائج جوجل..." maxlength="160" dir="auto">${data.seo_description || ''}</textarea>
    </div>
    <div class="form-row" style="margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.07)">
      <div class="modal-form-group">
        <label>الترتيب</label>
        <input type="number" class="form-input" id="f-sort_order" value="${data.sort_order || 0}" min="0" />
      </div>
      <div class="modal-form-group">
        <label>الحالة</label>
        <select class="form-input" id="f-is_active">
          <option value="1" ${data.is_active != 0 ? 'selected' : ''}>نشط</option>
          <option value="0" ${data.is_active == 0 ? 'selected' : ''}>معطل</option>
        </select>
      </div>
    </div>`,

  contact: (data = {}) => `
    <div class="form-row">
      <div class="modal-form-group">
        <label>النوع *</label>
        <select class="form-input" id="f-type">
          <option value="phone"    ${data.type === 'phone'    ? 'selected' : ''}>هاتف</option>
          <option value="whatsapp" ${data.type === 'whatsapp' ? 'selected' : ''}>واتساب</option>
          <option value="email"    ${data.type === 'email'    ? 'selected' : ''}>بريد إلكتروني</option>
        </select>
      </div>
      <div class="modal-form-group">
        <label>التسمية</label>
        <input type="text" class="form-input" id="f-label_ar" value="${data.label_ar || ''}" placeholder="خدمة العملاء" />
      </div>
    </div>
    <div class="modal-form-group">
      <label>القيمة *</label>
      <input type="text" class="form-input" id="f-value" value="${data.value || ''}" placeholder="920000000" dir="ltr" />
    </div>
    <div class="form-row">
      <div class="modal-form-group">
        <label>الترتيب</label>
        <input type="number" class="form-input" id="f-sort_order" value="${data.sort_order || 0}" min="0" />
      </div>
      <div class="modal-form-group">
        <label>الحالة</label>
        <select class="form-input" id="f-is_active">
          <option value="1" ${data.is_active != 0 ? 'selected' : ''}>نشط</option>
          <option value="0" ${data.is_active == 0 ? 'selected' : ''}>معطل</option>
        </select>
      </div>
    </div>`,
};

// ============================================================
// OPEN / CLOSE MODAL
// ============================================================
const MODAL_TITLES = {
  branch:   'فرع',
  brand:    'براند',
  social:   'منصة تواصل',
  contact:  'معلومة تواصل',
  category: 'قسم',
};

function openModal(type, data = {}) {
  state.editTable = type === 'branch'    ? 'branches'     :
                    type === 'brand'     ? 'brands'       :
                    type === 'social'    ? 'social_media' :
                    type === 'contact'   ? 'contact_info' :
                    type === 'category'  ? 'categories'   : type;

  const isEdit = !!state.editId;
  document.getElementById('modal-title').textContent = (isEdit ? 'تعديل ' : 'إضافة ') + (MODAL_TITLES[type] || type);
  document.getElementById('modal-body').innerHTML    = FORMS[type](data);
  document.getElementById('modal-overlay').classList.add('open');

  if (type === 'category') {
    // Decode HTML entities in body before passing to Quill
    const rawBody = data.body || '';
    setTimeout(() => initCategoryEditor(rawBody), 60);
  }
}

function closeModal() {
  document.getElementById('modal-overlay').classList.remove('open');
  state.editId    = null;
  state.editTable = null;
  if (quillInstance) { quillInstance = null; }
}

// ============================================================
// SAVE RECORD
// ============================================================
async function saveRecord() {
  const btn = document.getElementById('modal-save-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';

  try {
    const body = collectFormData();
    console.log(body)
    let res;

    if (state.editId) {
      res = await apiPut(state.editTable, state.editId, body);
    } else {
      res = await apiPost(state.editTable, body);
    }

    if (res.success) {
      showToast(res.message || 'تم الحفظ بنجاح', 'success');
      closeModal();
      await loadAll();
    } else {
      showToast(res.message || 'حدث خطأ', 'error');
    }
  } catch (err) {
    showToast('فشل الاتصال بالخادم', 'error');
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-save"></i> حفظ';
}

function collectFormData() {
  // Sync Quill → hidden textarea before collection
  const quillWrap = document.getElementById('quill-wrap');
  const bodyField = document.getElementById('f-body');
  if (quillInstance && quillWrap && quillWrap.style.display !== 'none' && bodyField) {
    bodyField.value = quillInstance.root.innerHTML;
  }

  const fields = document.querySelectorAll('#modal-body [id^="f-"]');
  const body = {};
  fields.forEach(el => {
    const key = el.id.replace('f-', '');
    body[key] = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value;
  });
  return body;
}

// ============================================================
// EDIT RECORD
// ============================================================
async function editRecord(table, id) {
  try {
    const res = await fetch(`${API_BASE}admin_crud.php?table=${table}&id=${id}`);
    const data = await res.json();
    if (!data.success) throw new Error(data.message);

    state.editId = id;
    const typeMap = {
      branches:    'branch',
      brands:      'brand',
      social_media:'social',
      contact_info:'contact',
      categories:  'category',
    };
    openModal(typeMap[table], data.data);
  } catch (err) {
    showToast('فشل تحميل البيانات: ' + err.message, 'error');
  }
}

// ============================================================
// TOGGLE ACTIVE
// ============================================================
async function toggleRecord(table, id) {
  try {
    const res = await apiToggle(table, id);
    if (res.success) {
      showToast(res.message, 'success');
      await loadAll();
    } else {
      showToast(res.message || 'حدث خطأ', 'error');
    }
  } catch {
    showToast('فشل الاتصال بالخادم', 'error');
  }
}

// ============================================================
// DELETE
// ============================================================
function confirmDelete(table, id) {
  state.deleteId    = id;
  state.deleteTable = table;
  document.getElementById('confirm-overlay').classList.add('open');
}

function closeConfirm() {
  document.getElementById('confirm-overlay').classList.remove('open');
  state.deleteId    = null;
  state.deleteTable = null;
}

document.getElementById('confirm-delete-btn').addEventListener('click', async () => {
  if (!state.deleteId || !state.deleteTable) return;

  try {
    const res = await apiDelete(state.deleteTable, state.deleteId);
    if (res.success) {
      showToast('تم الحذف بنجاح', 'success');
      closeConfirm();
      await loadAll();
    } else {
      showToast(res.message || 'حدث خطأ', 'error');
    }
  } catch {
    showToast('فشل الاتصال بالخادم', 'error');
  }
});

// ============================================================
// BRAND LOGO UPLOAD
// ============================================================
async function previewAndUpload(input) {
  const file = input.files[0];
  if (!file) return;

  // Instant local preview
  const reader = new FileReader();
  reader.onload = (e) => {
    const preview = document.getElementById('upload-preview');
    preview.style.display = 'block';
    preview.innerHTML = `
      <div class="logo-uploading-preview">
        <img src="${e.target.result}" alt="preview" />
        <span class="uploading-badge"><i class="fas fa-spinner fa-spin"></i> جاري الرفع...</span>
      </div>`;

    // Hide the upload button if visible
    const btn = document.getElementById('upload-area-btn');
    if (btn) btn.style.display = 'none';
  };
  reader.readAsDataURL(file);

  // Upload to server
  const formData = new FormData();
  formData.append('logo', file);
  const isArticleCover = !!document.getElementById('f-cover_image');
  const uploadType     = isArticleCover ? 'cover' : 'logo';

  try {
    const res  = await fetch(`../api/upload.php?type=${uploadType}`, { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
      const urlField = document.getElementById('f-cover_image') || document.getElementById('f-logo_url');
      if (urlField) urlField.value = data.url;
      // Update preview to show success
      const preview = document.getElementById('upload-preview');
      preview.innerHTML = `
        <div class="logo-uploading-preview">
          <img src="../${data.url}" alt="preview" />
          <span class="uploading-badge success"><i class="fas fa-check"></i> تم الرفع</span>
        </div>`;
      showToast('تم الرفع بنجاح', 'success');
    } else {
      showToast(data.message || 'فشل رفع الشعار', 'error');
      resetUploadArea();
    }
  } catch {
    showToast('فشل رفع الشعار', 'error');
    resetUploadArea();
  }
}

function removeLogo() {
  document.getElementById('f-logo_url').value = '';
  document.getElementById('logo-zone').innerHTML = `
    <div class="upload-area" id="upload-area-btn" onclick="document.getElementById('logo-file').click()">
      <i class="fas fa-cloud-upload-alt"></i>
      <p>اضغط لرفع الشعار</p>
      <small style="color:#555">JPG · PNG · WebP · SVG (بحد أقصى 2MB)</small>
    </div>
    <div class="upload-preview" id="upload-preview"></div>`;
  showToast('سيتم حذف الشعار عند الحفظ', 'success');
}

function resetUploadArea() {
  const btn = document.getElementById('upload-area-btn');
  if (btn) btn.style.display = '';
  const preview = document.getElementById('upload-preview');
  if (preview) { preview.style.display = 'none'; preview.innerHTML = ''; }
}

// ============================================================
// TABLE SEARCH FILTER
// ============================================================
function filterTable(tableId, query) {
  const table = document.getElementById(tableId);
  const rows  = table.querySelectorAll('tbody tr');
  const q     = query.toLowerCase();

  rows.forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

// ============================================================
// SIDEBAR MOBILE
// ============================================================
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebar-overlay').classList.remove('show');
}

function initSidebar() {
  document.getElementById('menu-toggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebar-overlay').classList.toggle('show');
  });

  document.getElementById('sidebar-overlay').addEventListener('click', closeSidebar);
}

// ============================================================
// TRACKING CODES (Google Analytics / GTM)
// ============================================================
async function loadTrackingCodes() {
  try {
    const res  = await fetch('../api/settings.php?admin=1');
    const data = await res.json();
    if (!data.success) return;
    data.data.forEach(row => {
      const el = document.getElementById(row.key);
      if (el) el.value = row.value || '';
    });
  } catch (_) {}
}

async function saveTrackingCodes() {
  const btn = document.querySelector('#tab-tracking .btn-add');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';

  const body = {
    header_code: document.getElementById('header_code').value,
    body_code:   document.getElementById('body_code').value,
  };

  try {
    const res  = await fetch('../api/settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message || 'تم الحفظ بنجاح', 'success');
    } else {
      showToast(data.message || 'حدث خطأ', 'error');
    }
  } catch {
    showToast('فشل الاتصال بالخادم', 'error');
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-save"></i> حفظ الكودات';
}

// ============================================================
// LOGOUT
// ============================================================
document.getElementById('logout-btn').addEventListener('click', async () => {
  try {
    await fetch('../api/admin_login.php?action=logout');
  } catch (_) {}
  window.location.href = 'index.html';
});

// ============================================================
// CLOSE MODALS ON OVERLAY CLICK
// ============================================================
document.getElementById('modal-overlay').addEventListener('click', (e) => {
  if (e.target === document.getElementById('modal-overlay')) closeModal();
});
document.getElementById('confirm-overlay').addEventListener('click', (e) => {
  if (e.target === document.getElementById('confirm-overlay')) closeConfirm();
});

// ============================================================
// HELPERS
// ============================================================
function statusBadge(active) {
  return active
    ? `<span class="status-badge status-active"><i class="fas fa-check-circle"></i> نشط</span>`
    : `<span class="status-badge status-inactive"><i class="fas fa-times-circle"></i> معطل</span>`;
}

function truncate(str, len) {
  if (!str) return '';
  return str.length > len ? str.substring(0, len) + '...' : str;
}

// ============================================================
// AUTH CHECK & INIT
// ============================================================
async function init() {
  // Check session
  try {
    const res  = await fetch('../api/admin_login.php?action=check');
    const data = await res.json();
    if (!data.success) {
      window.location.href = 'index.html';
      return;
    }
    document.getElementById('user-name').textContent = data.admin?.name || 'مدير';
  } catch {
    window.location.href = 'index.html';
    return;
  }

  initTabs();
  initSidebar();
  await Promise.all([loadAll(), loadTrackingCodes(), loadSliderSettings(), loadPerfSettings()]);
}

init();
