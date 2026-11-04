document.querySelectorAll('[data-table-search]').forEach(input=>{
  input.addEventListener('input',()=>{
    const table=document.getElementById(input.dataset.tableSearch); if(!table)return;
    const q=input.value.toLocaleLowerCase('es');
    table.querySelectorAll('tbody tr').forEach(row=>row.style.display=row.innerText.toLocaleLowerCase('es').includes(q)?'':'none');
  });
});
(function(){
 const tbody=document.getElementById('sale-lines'); if(!tbody)return;
 const products=window.ERP_PRODUCTS||[];
 const money=n=>new Intl.NumberFormat('es-CO',{style:'currency',currency:'COP',maximumFractionDigits:2}).format(n);
 function recalc(){
  let total=0;
  tbody.querySelectorAll('tr').forEach(row=>{
   const sel=row.querySelector('select'); const qty=Number(row.querySelector('input[name="cantidad[]"]').value||0);
   const p=products.find(x=>String(x.id)===sel.value); const line=p?Math.max(0,qty)*p.price:0;
   row.querySelector('.line-total').textContent=money(line); total+=line;
  });
  document.getElementById('subtotal-preview').textContent=money(total);
 }
 function addLine(){
  const row=document.createElement('tr');
  const options=products.map(p=>`<option value="${p.id}">${String(p.name).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]))}</option>`).join('');
  row.innerHTML=`<td><select name="producto[]" required><option value="">Selecciona producto</option>${options}</select></td><td class="stock-cell">—</td><td><input type="number" name="cantidad[]" min=".001" step=".001" value="1" required></td><td class="price-cell">—</td><td class="line-total">$ 0</td><td><button type="button" class="btn danger-btn remove-line">×</button></td>`;
  const sel=row.querySelector('select');
  sel.addEventListener('change',()=>{const p=products.find(x=>String(x.id)===sel.value);row.querySelector('.stock-cell').textContent=p?p.stock:'—';row.querySelector('.price-cell').textContent=p?money(p.price):'—';recalc();});
  row.querySelector('input').addEventListener('input',recalc);
  row.querySelector('.remove-line').addEventListener('click',()=>{row.remove();recalc();});
  tbody.appendChild(row);
 }
 document.getElementById('add-line').addEventListener('click',addLine);
 if(products.length)addLine();
 document.getElementById('sale-form').addEventListener('submit',e=>{if(!tbody.querySelector('select')||!Array.from(tbody.querySelectorAll('select')).some(s=>s.value)){e.preventDefault();alert('Agrega al menos un producto disponible.');}});
})();
