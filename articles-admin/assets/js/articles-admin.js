/* ============================================================
   Articles Admin Panel JS
   لوحة إدارة المقالات المستقلة
============================================================ */

"use strict";

const API = '../api/articles_api.php';

const state = {
  editId:    null,
  deleteId:  null,
  articles:  [],
};

// Quill rich editor instance
let quillInstance = null;

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
// LOAD ARTICLES
// ============================================================
async function loadArticles() {
  try {
    const res  = await fetch(`${API}?admin=1`);
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'فشل التحميل');
    state.articles = data.data;
    renderArticles(data.data);
  } catch (err) {
    showToast('فشل تحميل المقالات: ' + err.message, 'error');
  }
}

// ============================================================
// RENDER TABLE
// ============================================================
function renderArticles(rows) {
  const tbody = document.getElementById('articles-tbody');
  tbody.innerHTML = '';

  rows.forEach((row, i) => {
    const coverHtml = row.cover_image
      ? `<img src="../${row.cover_image}" style="width:50px;height:50px;object-fit:cover;border-radius:6px">`
      : '-';

    const featuredBadge = row.is_featured
      ? `<span class="badge bg-warning" style="background:#FFCF06;color:#000;padding:2px 8px;border-radius:4px;font-size:11px">مميز</span>`
      : `<span style="color:#777;font-size:11px">عادي</span>`;

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${i + 1}</td>
      <td>${coverHtml}</td>
      <td><strong style="color:#fff">${row.title}</strong></td>
      <td>${row.category || '-'}</td>
      <td>${row.author_name || '-'}</td>
      <td>${featuredBadge}</td>
      <td>${statusBadge(row.is_active)}</td>
      <td>${row.view_count || 0}</td>
      <td style="font-size:12px">${row.published_at ? row.published_at.substring(0,10) : '-'}</td>
      <td>
        <div class="actions">
          <a  class="btn-icon" title="معاينة"            href="../article.php?slug=${row.slug}" target="_blank"><i class="fas fa-eye"></i></a>
          <button class="btn-icon" title="تعديل"         onclick="editArticle(${row.id})"><i class="fas fa-edit"></i></button>
          <button class="btn-icon" title="تفعيل/تعطيل"  onclick="toggleArticle(${row.id})"><i class="fas fa-toggle-on"></i></button>
          <button class="btn-icon del" title="حذف"       onclick="confirmDelete(${row.id})"><i class="fas fa-trash"></i></button>
        </div>
      </td>`;
    tbody.appendChild(tr);
  });

  document.getElementById('articles-count').textContent = `(${rows.length} مقال)`;
  document.getElementById('badge-articles').textContent = rows.length;
}

function statusBadge(active) {
  return active
    ? `<span class="status-badge status-active"><i class="fas fa-check-circle"></i> نشط</span>`
    : `<span class="status-badge status-inactive"><i class="fas fa-times-circle"></i> معطل</span>`;
}

function filterTable(tableId, query) {
  const table = document.getElementById(tableId);
  const rows  = table.querySelectorAll('tbody tr');
  const q     = query.toLowerCase();
  rows.forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

// ============================================================
// MODAL FORM
// ============================================================
function articleForm(data = {}) {
  return `
    <div class="form-row">
      <div class="modal-form-group">
        <label>العنوان *</label>
        <input type="text" class="form-input" id="f-title" value="${esc(data.title)}" />
      </div>
      <div class="modal-form-group">
        <label>Slug</label>
        <input type="text" class="form-input" id="f-slug" value="${esc(data.slug)}" dir="ltr" />
      </div>
    </div>

    <div class="modal-form-group">
      <label>الوصف المختصر</label>
      <textarea class="form-input" id="f-excerpt" rows="2">${esc(data.excerpt)}</textarea>
    </div>

    <div class="modal-form-group">
      <label>المحتوى</label>
      <div id="quill-editor-container">
        <div id="quill-editor"></div>
      </div>
      <input type="hidden" id="f-body" value="" />
    </div>

    <div class="modal-form-group">
      <label>صورة المقال</label>
      <div id="cover-zone">
        ${data.cover_image
          ? `<div class="logo-current-wrap">
               <div class="logo-current-preview">
                 <img src="../${esc(data.cover_image)}" id="cover-current-img" />
               </div>
               <div class="logo-current-actions">
                 <button type="button" class="btn-change-logo" onclick="document.getElementById('cover-file').click()">تغيير الصورة</button>
                 <button type="button" class="btn-remove-logo"  onclick="removeCover()">حذف</button>
               </div>
             </div>`
          : `<div class="upload-area" onclick="document.getElementById('cover-file').click()">
               <i class="fas fa-cloud-upload-alt"></i>
               <p>رفع صورة</p>
             </div>`
        }
        <div id="upload-preview"></div>
      </div>
      <input type="file" id="cover-file" accept="image/*" style="display:none" onchange="previewAndUpload(this)" />
      <input type="hidden" id="f-cover_image" value="${esc(data.cover_image)}" />
    </div>

    <div class="form-row">
      <div class="modal-form-group">
        <label>التصنيف</label>
        <input type="text" class="form-input" id="f-category" value="${esc(data.category)}" />
      </div>
      <div class="modal-form-group">
        <label>الكاتب</label>
        <input type="text" class="form-input" id="f-author_name" value="${esc(data.author_name || 'مخازن العناية')}" />
      </div>
    </div>

    <div class="form-row">
      <div class="modal-form-group">
        <label>مميز</label>
        <select class="form-input" id="f-is_featured">
          <option value="1" ${data.is_featured ? 'selected' : ''}>نعم</option>
          <option value="0" ${!data.is_featured ? 'selected' : ''}>لا</option>
        </select>
      </div>
      <div class="modal-form-group">
        <label>الحالة</label>
        <select class="form-input" id="f-is_active">
          <option value="1" ${data.is_active != 0 ? 'selected' : ''}>نشط</option>
          <option value="0" ${data.is_active == 0 ? 'selected' : ''}>معطل</option>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="modal-form-group">
        <label>الترتيب</label>
        <input type="number" class="form-input" id="f-sort_order" value="${data.sort_order || 0}" />
      </div>
      <div class="modal-form-group">
        <label>تاريخ النشر</label>
        <input type="datetime-local" class="form-input" id="f-published_at" value="${(data.published_at || '').replace(' ', 'T').substring(0, 16)}" />
      </div>
    </div>

    <hr />
    <h4>SEO</h4>

    <div class="modal-form-group">
      <label>SEO Title</label>
      <input type="text" class="form-input" id="f-seo_title" value="${esc(data.seo_title)}" />
    </div>
    <div class="modal-form-group">
      <label>SEO Description</label>
      <textarea class="form-input" id="f-seo_description" rows="2">${esc(data.seo_description)}</textarea>
    </div>
    <div class="modal-form-group">
      <label>Tags</label>
      <input type="text" class="form-input" id="f-tags" value="${esc(data.tags)}" placeholder="مفصولة بفواصل" />
    </div>
  `;
}

function esc(val) {
  if (!val) return '';
  return String(val)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

// ============================================================
// QUILL RICH EDITOR
// ============================================================
function initQuillEditor(content = '') {
  quillInstance = new Quill('#quill-editor', {
    theme: 'snow',
    direction: 'rtl',
    placeholder: 'اكتب محتوى المقال هنا...',
    modules: {
      toolbar: [
        [{ header: [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        [{ align: [] }],
        ['link', 'blockquote'],
        ['clean'],
      ],
    },
  });

  // تحميل المحتوى الموجود عند التعديل
  if (content) {
    quillInstance.clipboard.dangerouslyPasteHTML(content);
  }
}

// ============================================================
// OPEN / CLOSE MODAL
// ============================================================
function openModal(type, data = {}) {
  const isEdit = !!state.editId;
  document.getElementById('modal-title').textContent = isEdit ? 'تعديل مقال' : 'إضافة مقال';
  document.getElementById('modal-body').innerHTML    = articleForm(data);
  document.getElementById('modal-overlay').classList.add('open');
  // تهيئة Quill بعد ظهور الـ DOM
  setTimeout(() => initQuillEditor(data.body || ''), 50);
}

function closeModal() {
  document.getElementById('modal-overlay').classList.remove('open');
  state.editId    = null;
  quillInstance   = null;
}

// ============================================================
// SAVE ARTICLE
// ============================================================
async function saveArticle() {
  const btn = document.getElementById('modal-save-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';

  try {
    const body = collectForm();
    let res, data;

    if (state.editId) {
      res  = await fetch(`${API}?id=${state.editId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
    } else {
      res  = await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
    }
    data = await res.json();

    if (data.success) {
      showToast(data.message || 'تم الحفظ بنجاح');
      closeModal();
      await loadArticles();
    } else {
      showToast(data.message || 'حدث خطأ', 'error');
    }
  } catch (err) {
    showToast('فشل الاتصال بالخادم', 'error');
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-save"></i> حفظ';
}

function collectForm() {
  // مزامنة محتوى Quill إلى الـ hidden input قبل الجمع
  if (quillInstance) {
    document.getElementById('f-body').value = quillInstance.root.innerHTML;
  }
  const fields = document.querySelectorAll('#modal-body [id^="f-"]');
  const body   = {};
  fields.forEach(el => {
    const key = el.id.replace('f-', '');
    body[key] = el.value;
  });
  return body;
}

// ============================================================
// EDIT ARTICLE
// ============================================================
async function editArticle(id) {
  try {
    const res  = await fetch(`${API}?admin=1&id=${id}`);
    const data = await res.json();
    if (!data.success) throw new Error(data.message);
    state.editId = id;
    openModal('article', data.data);
  } catch (err) {
    showToast('فشل تحميل البيانات: ' + err.message, 'error');
  }
}

// ============================================================
// TOGGLE ACTIVE
// ============================================================
async function toggleArticle(id) {
  try {
    const res  = await fetch(`${API}?id=${id}`, { method: 'PATCH' });
    const data = await res.json();
    if (data.success) {
      showToast(data.message);
      await loadArticles();
    } else {
      showToast(data.message || 'حدث خطأ', 'error');
    }
  } catch {
    showToast('فشل الاتصال بالخادم', 'error');
  }
}

// ============================================================
// DELETE
// ============================================================
function confirmDelete(id) {
  state.deleteId = id;
  document.getElementById('confirm-overlay').classList.add('open');
}

function closeConfirm() {
  document.getElementById('confirm-overlay').classList.remove('open');
  state.deleteId = null;
}

document.getElementById('confirm-delete-btn').addEventListener('click', async () => {
  if (!state.deleteId) return;
  try {
    const res  = await fetch(`${API}?id=${state.deleteId}`, { method: 'DELETE' });
    const data = await res.json();
    if (data.success) {
      showToast('تم الحذف بنجاح');
      closeConfirm();
      await loadArticles();
    } else {
      showToast(data.message || 'حدث خطأ', 'error');
    }
  } catch {
    showToast('فشل الاتصال بالخادم', 'error');
  }
});

// ============================================================
// IMAGE UPLOAD (WebP)
// ============================================================
async function previewAndUpload(input) {
  const file = input.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = (e) => {
    const preview = document.getElementById('upload-preview');
    preview.style.display = 'block';
    preview.innerHTML = `
      <div class="logo-uploading-preview">
        <img src="${e.target.result}" alt="preview" />
        <span class="uploading-badge"><i class="fas fa-spinner fa-spin"></i> جاري الرفع...</span>
      </div>`;
  };
  reader.readAsDataURL(file);

  const formData = new FormData();
  formData.append('logo', file);

  try {
    const res  = await fetch('../api/upload.php?type=cover', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
      document.getElementById('f-cover_image').value = data.url;
      const preview = document.getElementById('upload-preview');
      preview.innerHTML = `
        <div class="logo-uploading-preview">
          <img src="../${data.url}" alt="preview" />
          <span class="uploading-badge success"><i class="fas fa-check"></i> تم الرفع</span>
        </div>`;
      showToast('تم الرفع بنجاح');
    } else {
      showToast(data.message || 'فشل رفع الصورة', 'error');
      resetUploadArea();
    }
  } catch {
    showToast('فشل رفع الصورة', 'error');
    resetUploadArea();
  }
}

function removeCover() {
  document.getElementById('f-cover_image').value = '';
  document.getElementById('cover-zone').innerHTML = `
    <div class="upload-area" onclick="document.getElementById('cover-file').click()">
      <i class="fas fa-cloud-upload-alt"></i>
      <p>رفع صورة</p>
    </div>
    <div id="upload-preview"></div>`;
  showToast('سيتم حذف الصورة عند الحفظ');
}

function resetUploadArea() {
  const preview = document.getElementById('upload-preview');
  if (preview) { preview.style.display = 'none'; preview.innerHTML = ''; }
}

// ============================================================
// SIDEBAR MOBILE
// ============================================================
document.getElementById('menu-toggle').addEventListener('click', () => {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebar-overlay').classList.toggle('show');
});
document.getElementById('sidebar-overlay').addEventListener('click', () => {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebar-overlay').classList.remove('show');
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
// LOGOUT
// ============================================================
document.getElementById('logout-btn').addEventListener('click', async () => {
  try { await fetch('../api/articles_login.php?action=logout'); } catch (_) {}
  window.location.href = 'index.html';
});

// ============================================================
// INIT
// ============================================================
async function init() {
  try {
    const res  = await fetch('../api/articles_login.php?action=check');
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

  await loadArticles();
}

init();
