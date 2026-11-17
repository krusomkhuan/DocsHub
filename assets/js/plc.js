// PLC: เพิ่ม/ลบแถวในตาราง <tbody data-rows="ชื่อ"> ใช้แม่แบบ <template data-template="ชื่อ"> (แทน __i__ ด้วยลำดับใหม่)
(function () {
  var counter = Date.now();
  document.addEventListener('click', function (e) {
    var add = e.target.closest('[data-add-row]');
    if (add) {
      e.preventDefault();
      var name = add.getAttribute('data-add-row');
      var tpl = document.querySelector('template[data-template="' + name + '"]');
      var body = document.querySelector('[data-rows="' + name + '"]');
      body.insertAdjacentHTML('beforeend', tpl.innerHTML.replace(/__i__/g, 'n' + (counter++)));
      renumber(body);
      var first = body.lastElementChild && body.lastElementChild.querySelector('input,textarea,select');
      if (first) first.focus();
      return;
    }
    var del = e.target.closest('[data-del-row]');
    if (del) {
      e.preventDefault();
      var tbody = del.closest('[data-rows]');
      del.closest('tr').remove();
      renumber(tbody);
    }
  });
  function renumber(body) {
    body.querySelectorAll('[data-row-no]').forEach(function (el, i) { el.textContent = i + 1; });
  }
})();
