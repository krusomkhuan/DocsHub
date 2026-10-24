// ยืนยันก่อนดำเนินการ: <form data-confirm="ข้อความ">
document.addEventListener('submit', function (e) {
  var msg = e.target.getAttribute('data-confirm');
  if (msg && !confirm(msg)) e.preventDefault();
});

// โหลดหมวดหมู่ย่อยตามประเภทเอกสาร: <select data-categories-for="#typeSelect">
(function () {
  var catSelect = document.querySelector('[data-categories]');
  var typeSelect = document.querySelector('[data-type-select]');
  if (!catSelect || !typeSelect) return;
  var all = JSON.parse(catSelect.getAttribute('data-categories'));
  var selected = catSelect.getAttribute('data-selected') || '';
  function fill() {
    var t = typeSelect.value;
    catSelect.innerHTML = '<option value="">— ไม่ระบุ —</option>';
    all.filter(function (c) { return String(c.type_id) === t; }).forEach(function (c) {
      var o = document.createElement('option');
      o.value = c.id; o.textContent = c.name;
      if (String(c.id) === selected) o.selected = true;
      catSelect.appendChild(o);
    });
    // แสดง/ซ่อนช่องข้อมูลตามตัวเลือกของประเภทเอกสาร (ตั้งค่าที่หลังบ้าน > ประเภทเอกสาร)
    var opt = typeSelect.options[typeSelect.selectedIndex];
    var flags = opt && opt.getAttribute('data-flags') ? opt.getAttribute('data-flags').split(' ') : [];
    document.querySelectorAll('[data-show-for]').forEach(function (el) {
      el.style.display = flags.indexOf(el.getAttribute('data-show-for')) >= 0 ? '' : 'none';
    });
    var ownerLabel = document.querySelector('label[data-owner-label]');
    if (ownerLabel) ownerLabel.textContent = (opt && opt.getAttribute('data-owner-label')) || 'ผู้รับผิดชอบ';
  }
  typeSelect.addEventListener('change', function () { selected = ''; fill(); });
  fill();
})();

// รายงานการไปราชการ: ย่อภาพในเบราว์เซอร์ก่อนอัปโหลด แสดงตัวอย่าง และช่องคำบรรยายภาพ
(function () {
  var input = document.querySelector('[data-trip-images]');
  if (!input) return;
  var preview = document.querySelector('[data-trip-preview]');
  var status = document.querySelector('[data-trip-status]');
  var submit = input.form.querySelector('button:not([type=button])');
  var MAX_SIDE = 1600;

  function resize(file) {
    return new Promise(function (resolve) {
      if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return resolve(file);
      var img = new Image();
      var src = URL.createObjectURL(file);
      img.onload = function () {
        var s = Math.min(1, MAX_SIDE / Math.max(img.naturalWidth, img.naturalHeight));
        if (s >= 1 && file.size < 1500000) { URL.revokeObjectURL(src); return resolve(file); }
        var c = document.createElement('canvas');
        c.width = Math.round(img.naturalWidth * s);
        c.height = Math.round(img.naturalHeight * s);
        var ctx = c.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, c.width, c.height);
        ctx.drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(src);
        c.toBlob(function (b) {
          resolve(b ? new File([b], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }) : file);
        }, 'image/jpeg', 0.85);
      };
      img.onerror = function () { URL.revokeObjectURL(src); resolve(file); };
      img.src = src;
    });
  }

  input.addEventListener('change', function () {
    var files = Array.prototype.slice.call(input.files);
    var max = parseInt(input.getAttribute('data-max'), 10) || 30;
    if (files.length > max) {
      alert('เลือกภาพได้อีกไม่เกิน ' + max + ' ภาพ ระบบจะใช้ ' + max + ' ภาพแรก');
      files = files.slice(0, max);
    }
    preview.innerHTML = '';
    if (!files.length) { status.textContent = ''; return; }
    status.textContent = 'กำลังเตรียมภาพ...';
    if (submit) submit.disabled = true;
    Promise.all(files.map(resize)).then(function (out) {
      try {
        var dt = new DataTransfer();
        out.forEach(function (f) { dt.items.add(f); });
        input.files = dt.files;
      } catch (e) { out = files; } // เบราว์เซอร์เก่า: ส่งไฟล์เดิม เซิร์ฟเวอร์จะย่อให้
      var total = 0;
      out.forEach(function (f) {
        total += f.size;
        var col = document.createElement('div');
        col.className = 'col-6 col-md-4 col-lg-3';
        col.innerHTML = '<div class="card h-100"><img class="card-img-top" style="aspect-ratio:4/3;object-fit:cover" alt="">' +
          '<div class="card-body p-2"><input class="form-control form-control-sm" name="new_caption[]" maxlength="255" placeholder="คำบรรยายภาพ"></div></div>';
        col.querySelector('img').src = URL.createObjectURL(f);
        preview.appendChild(col);
      });
      status.textContent = 'ภาพใหม่ ' + out.length + ' ภาพ · รวม ' + (total / 1048576).toFixed(1) + ' MB';
      if (submit) submit.disabled = false;
    });
  });
})();

// ปุ่มคัดลอกลิงก์: <button data-copy="https://...">
document.addEventListener('click', function (e) {
  var btn = e.target.closest('[data-copy]');
  if (!btn) return;
  var text = btn.getAttribute('data-copy');
  var done = function () {
    var old = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check2 me-1"></i>คัดลอกแล้ว';
    setTimeout(function () { btn.innerHTML = old; }, 1500);
  };
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(done);
  } else { // http ที่ไม่ใช่ localhost: ใช้วิธีเดิม
    var ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); done(); } catch (err) { prompt('คัดลอกลิงก์:', text); }
    ta.remove();
  }
});
