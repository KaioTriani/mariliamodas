const $ = selector => document.querySelector(selector);
const sizes = ['P', 'M', 'G', 'GG', 'Preciso de ajuda', 'Não se aplica'];
let selectedColors = [];
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const escapeHTML = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const normalize = text => text.toLocaleLowerCase('pt-BR').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
const getProduct = id => products.find(product => product.id === id);

function readStored(key) {
  try {
    const value = JSON.parse(localStorage.getItem(key));
    return Array.isArray(value) ? value : [];
  } catch { return []; }
}
// Separate storage prevents old demonstration products from entering the new cart.
let favorites = readStored('marilia-v2-favorites').filter(id => getProduct(id));
let bag = readStored('marilia-v2-bag').filter(row => row && getProduct(row.id) && sizes.includes(row.size) && Number.isInteger(row.qty) && row.qty > 0 && row.qty <= 99);
let category = 'Todas';
let favoritesOnly = false;
let selectedProduct = null;
let selectedSize = '';
let toastTimer;

function counts() {
  $('#bag-count').textContent = bag.reduce((total, row) => total + row.qty, 0);
  $('#favorite-count').textContent = favorites.length || '';
  $('[data-action="favorites"]').setAttribute('aria-pressed', String(favoritesOnly));
}
function persist() {
  try {
    localStorage.setItem('marilia-v2-favorites', JSON.stringify(favorites));
    localStorage.setItem('marilia-v2-bag', JSON.stringify(bag));
  } catch { /* Shopping remains usable if browser storage is unavailable. */ }
  counts();
}
function toast(message) {
  $('#toast').textContent = message;
  $('#toast').classList.add('visible');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => $('#toast').classList.remove('visible'), 2800);
}
function renderProducts() {
  const query = normalize($('#search').value);
  const detail = $('#filter-detail').value;
  const list = products.filter(product =>
    (category === 'Todas' || product.category === category) &&
    (!favoritesOnly || favorites.includes(product.id)) &&
    (!selectedColors.length || selectedColors.includes(product.color)) &&
    (!detail || product.tags.includes(detail)) &&
    normalize(`${product.name} ${product.category} ${product.color} ${product.detail}`).includes(query)
  );
  if ($('#filter-sort').value === 'az') list.sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'));
  if ($('#filter-sort').value === 'za') list.sort((a, b) => b.name.localeCompare(a.name, 'pt-BR'));
  renderChips();
  $('#result-count').textContent = `${list.length} ${list.length === 1 ? 'peça' : 'peças'} ${favoritesOnly ? 'nos seus favoritos' : 'na seleção'}`;
  $('#products').innerHTML = list.length ? list.map(product => `
    <article class="product-card ${product.real ? 'real-photo' : ''}" style="--card-order:${list.indexOf(product)%4}">
      <div class="product-image">
        <button class="photo-button" data-product="${product.id}" aria-label="Ver ${product.name}">
          <img src="${product.image}" alt="${product.real ? 'Foto real' : 'Recriação com IA'}: ${product.name}" loading="lazy">
        </button>
        <span class="piece-number">${product.number}</span>
        <button class="favorite ${favorites.includes(product.id) ? 'selected' : ''}" data-favorite="${product.id}" aria-label="Favoritar ${product.name}" aria-pressed="${favorites.includes(product.id)}">${favorites.includes(product.id) ? '♥' : '♡'}</button>
        <button class="quick-view" data-product="${product.id}">Ver de perto <span>↗</span></button>
      </div>
      <div class="product-info">
        <div class="product-top"><button class="product-name" data-product="${product.id}">${product.name}</button><span class="color-dot" style="--color:${product.swatch}" title="${product.color}"></span></div>
        <p>${product.detail}</p>
        <div class="product-bottom"><span>Valor sob consulta</span><button data-product="${product.id}">Quero essa peça +</button></div>
        <a class="image-note" href="${product.source}" target="_blank" rel="noopener">${product.real ? 'Foto real da loja · ampliar ↗' : 'Recriação com IA · ver referência ↗'}</a>
      </div>
    </article>`).join('') : '<div class="empty">Nenhuma peça nesta seleção.<button data-action="clear">Voltar para todas as peças</button></div>';
  document.querySelectorAll('[data-category]').forEach(button => {
    const selected = button.dataset.category === category;
    button.classList.toggle('selected', selected);
    button.setAttribute('aria-pressed', String(selected));
  });
  counts();
}
function openProduct(id) {
  const product = getProduct(id);
  if (!product) return;
  selectedProduct = product;
  selectedSize = product.category === 'Bolsas' ? 'Não se aplica' : '';
  $('#product-dialog').innerHTML = `
    <button class="close" data-close="product-dialog" aria-label="Fechar detalhes">×</button>
    <div class="modal-layout">
      <div><div class="modal-photo"><img src="${product.image}" alt="${product.real ? 'Foto real' : 'Recriação com IA'} de ${product.name}"></div>
        <div class="gallery-controls" role="group" aria-label="Enquadramento"><button data-gallery="full" class="selected" aria-pressed="true">Look completo</button><button data-gallery="detail" aria-pressed="false">Detalhe ampliado</button></div>
      </div>
      <div class="modal-info">
        <p class="eyebrow">${product.number} / ${product.category.toUpperCase()} / ${product.color.toUpperCase()}</p>
        <h2 id="product-title">${product.name}</h2>
        <p>${product.description}</p>
        <a class="text-link" href="${product.source}" target="_blank" rel="noopener">${product.real ? 'Abrir foto original ↗' : 'Veja a referência original ↗'}</a>
        <p id="size-label">${product.category === 'Bolsas' ? 'Dimensões e alças: consulte a loja' : 'Qual tamanho você costuma usar?'}</p>
        <div class="size-options" role="group" aria-labelledby="size-label">${(product.category === 'Bolsas' ? [] : sizes.filter(s=>s!=='Não se aplica')).map(size => `<button data-size="${size}" aria-pressed="false">${size}</button>`).join('')}</div>
        <small>${product.category === 'Bolsas' ? 'Não é necessário selecionar tamanho. Confirme dimensões, material e disponibilidade.' : 'Informe sua preferência. Tamanhos, medidas, composição e estoque serão confirmados pela loja.'}</small>
        <button class="button dark" data-action="add">Adicionar à sacola +</button>
        <button class="button secondary-button" data-action="buy">Consultar pelo WhatsApp ↗</button>
        <small>${product.real ? 'Foto real enviada pela loja. Cores podem variar com iluminação e tela.' : 'Recriação com IA. Confira cor, textura e detalhes na referência original antes de comprar.'}</small>
      </div>
    </div>`;
  $('#product-dialog').setAttribute('aria-labelledby', 'product-title');
  $('#product-dialog').showModal();
}
function requireSize() {
  if (selectedSize) return true;
  toast('Selecione um tamanho ou “Preciso de ajuda”.');
  $('#product-dialog [data-size]').focus();
  return false;
}
function whatsapp(message) {
  window.open(`https://wa.me/5587988630420?text=${encodeURIComponent(message)}`, '_blank', 'noopener,noreferrer');
}
function renderBag() {
  const quantity = bag.reduce((sum, row) => sum + row.qty, 0);
  $('#bag-dialog').innerHTML = `
    <button class="close" data-close="bag-dialog" aria-label="Fechar sacola">×</button>
    <h2 id="bag-title">Suas escolhas (${quantity})</h2>
    ${bag.length ? bag.map((row, index) => {
      const product = getProduct(row.id);
      return `<div class="bag-row"><img src="${product.image}" alt="${product.name}"><div><h3>${product.name}</h3><p>Preferência: ${row.size} · ${product.color}</p><div class="quantity"><button data-quantity="${index}" data-delta="-1" aria-label="Diminuir quantidade de ${product.name}">−</button><span>${row.qty}</span><button data-quantity="${index}" data-delta="1" aria-label="Aumentar quantidade de ${product.name}">+</button><button class="remove" data-remove="${index}">Remover</button></div></div></div>`;
    }).join('') + '<p class="bag-summary">Valores, tamanhos e entrega: vamos combinar pelo WhatsApp.</p><a class="button dark" href="/checkout.html">Ir para o checkout ↗</a><button class="button secondary-button" data-action="checkout">Consultar pelo WhatsApp ↗</button><p class="bag-note">Revise sua seleção no checkout ou tire dúvidas com a loja.</p>' : '<p>Sua sacola ainda está vazia.</p><button class="button dark" data-close="bag-dialog">Continuar escolhendo ↗</button>'}`;
  $('#bag-dialog').setAttribute('aria-labelledby', 'bag-title');
}
function clearFilters() {
  category = 'Todas'; favoritesOnly = false;
  $('#search').value = ''; selectedColors = []; $('#filter-detail').value = ''; $('#filter-sort').value = 'editorial'; $('#filter-favorites').checked = false; document.querySelectorAll('[data-color]').forEach(el=>el.checked=false);
  renderProducts();
}
function closeMenu() {
  $('nav').classList.remove('open');
  $('[data-action="menu"]').setAttribute('aria-expanded', 'false');
}
document.addEventListener('click', event => {
  const button = event.target.closest('button, a');
  if (!button) return;
  const data = button.dataset;
  if (data.close) { $(`#${data.close}`).close(); return; }
  if (data.product) { openProduct(data.product); return; }
  if (data.browse) { clearFilters(); category=data.browse; renderProducts(); $('#pecas').scrollIntoView(); return; }
  if (data.hero) { changeHero(data.hero); return; }
  if (data.view) { $('#products').classList.toggle('compact',data.view==='compact'); document.querySelectorAll('[data-view]').forEach(el=>el.setAttribute('aria-pressed',String(el===button))); return; }
  if (data.removeFilter) { removeFilter(data.removeFilter); return; }
  if (data.category) { category = data.category; renderProducts(); return; }
  if (data.favorite) {
    favorites = favorites.includes(data.favorite) ? favorites.filter(id => id !== data.favorite) : [...favorites, data.favorite];
    persist(); renderProducts();
    // Keep keyboard focus after rebuilding the product list.
    document.querySelector(`[data-favorite="${data.favorite}"]`)?.focus({ preventScroll: true });
    return;
  }
  if (data.size) {
    selectedSize = data.size;
    document.querySelectorAll('[data-size]').forEach(option => {
      option.classList.toggle('selected', option === button);
      option.setAttribute('aria-pressed', String(option === button));
    });
    return;
  }
  if (data.gallery) {
    $('.modal-photo').classList.toggle('detail', data.gallery === 'detail');
    document.querySelectorAll('[data-gallery]').forEach(option => {
      option.classList.toggle('selected', option === button);
      option.setAttribute('aria-pressed', String(option === button));
    });
    return;
  }
  if (data.quantity !== undefined) {
    const index = Number(data.quantity);
    bag[index].qty = Math.min(99, bag[index].qty + Number(data.delta));
    if (bag[index].qty < 1) bag.splice(index, 1);
    persist(); renderBag(); return;
  }
  if (data.remove !== undefined) { bag.splice(Number(data.remove), 1); persist(); renderBag(); return; }
  switch (data.action) {
    case 'menu':
      $('nav').classList.toggle('open');
      button.setAttribute('aria-expanded', String($('nav').classList.contains('open')));
      break;
    case 'search':
      closeMenu(); $('#search-panel').hidden = false; $('#pecas').scrollIntoView(); $('#search').focus({ preventScroll: true }); break;
    case 'close-search': $('#search').value = ''; renderProducts(); break;
    case 'filters': $('#filters').hidden = !$('#filters').hidden; button.setAttribute('aria-expanded', String(!$('#filters').hidden)); break;
    case 'clear': clearFilters(); break;
    case 'favorites': favoritesOnly = !favoritesOnly; $('#filter-favorites').checked=favoritesOnly; renderProducts(); $('#pecas').scrollIntoView(); break;
    case 'bag': renderBag(); $('#bag-dialog').showModal(); break;
    case 'add': {
      if (!requireSize()) break;
      const row = bag.find(item => item.id === selectedProduct.id && item.size === selectedSize);
      if (row) row.qty = Math.min(99, row.qty + 1);
      else bag.push({ id: selectedProduct.id, size: selectedSize, qty: 1 });
      persist(); $('#product-dialog').close(); toast('Peça salva na sua sacola.'); if(!reducedMotion.matches) $('[data-action=bag]').animate([{transform:'scale(1)'},{transform:'scale(1.16)'},{transform:'scale(1)'}],{duration:400}); break;
    }
    case 'buy':
      if (requireSize()) whatsapp(`Olá, Marília! Gostei deste look: ${selectedProduct.name}, cor ${selectedProduct.color}. Meu tamanho de preferência: ${selectedSize}.\nPode confirmar disponibilidade, medidas, valor e entrega?`);
      break;
    case 'checkout':
      if (bag.length) whatsapp('Olá, Marília! Quero consultar esta seleção:\n\n' + bag.map(row => {
        const product = getProduct(row.id);
        return `${row.qty}x ${product.name} — ${product.color}\nTamanho de preferência: ${row.size}`;
      }).join('\n\n') + '\n\nPode confirmar disponibilidade, medidas, valores e frete?');
      break;
    case 'policy':
      $('#info-dialog').innerHTML = '<button class="close" data-close="info-dialog" aria-label="Fechar informações">×</button><h2 id="info-title">Trocas e devoluções</h2><p>Fale com a Marília para consultar a política oficial, as condições e o procedimento para a sua compra.</p><a class="button dark" href="https://wa.me/5587988630420" target="_blank" rel="noopener">Consultar a loja ↗</a>';
      $('#info-dialog').setAttribute('aria-labelledby', 'info-title'); $('#info-dialog').showModal(); break;
  }
  if (button.closest('nav') && button.getAttribute('href')) closeMenu();
});
$('#search').addEventListener('input', renderProducts);
$('#filter-detail').addEventListener('change', renderProducts);
$('#filter-favorites').addEventListener('change', event=>{favoritesOnly=event.target.checked;renderProducts();});
$('#color-options').addEventListener('change',()=>{selectedColors=[...document.querySelectorAll('[data-color]:checked')].map(el=>el.value);renderProducts();});
$('#filter-sort').addEventListener('change', renderProducts);
document.querySelectorAll('dialog').forEach(dialog => dialog.addEventListener('click', event => {
  if (event.target !== dialog) return;
  const rect = dialog.getBoundingClientRect();
  if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
}));

function renderChips() {
  const chips=[];
  if(category!=='Todas') chips.push(['category',category]);
  selectedColors.forEach(c=>chips.push(['color:'+c,c]));
  if($('#filter-detail').value) chips.push(['detail',$('#filter-detail').value]);
  if(favoritesOnly) chips.push(['favorites','Favoritos']);
  if($('#search').value.trim()) chips.push(['search','Busca: '+$('#search').value.trim()]);
  $('#active-filters').innerHTML=chips.map(([id,label])=>'<button data-remove-filter="'+escapeHTML(id)+'" aria-label="Remover filtro '+escapeHTML(label)+'">'+escapeHTML(label)+' <span>×</span></button>').join('');
  $('#filter-count').textContent=chips.length ? '('+chips.length+')' : '';
}
function removeFilter(key) {
  if(key==='category') category='Todas';
  if(key==='detail') $('#filter-detail').value='';
  if(key==='search') $('#search').value='';
  if(key==='favorites'){favoritesOnly=false;$('#filter-favorites').checked=false;}
  if(key.startsWith('color:')) {selectedColors=selectedColors.filter(c=>c!==key.slice(6));document.querySelectorAll('[data-color]').forEach(el=>el.checked=selectedColors.includes(el.value));}
  renderProducts();
}
function changeHero(id) {
  const p=getProduct(id), photo=$('.hero-photo img');
  if(!p) return;
  photo.src=p.image; photo.alt=(p.real?'Foto real: ':'Recriação com IA: ')+p.name;
  $('.hero-photo').classList.toggle('is-bag',p.category==='Bolsas');
  $('.photo-caption span').textContent=p.name;
  $('.photo-caption button').dataset.product=id;
  $('.photo-disclosure').textContent=p.real?'Foto real da loja':'Imagem recriada com IA a partir do perfil';
  document.querySelectorAll('[data-hero]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.hero===id)));
  if(!reducedMotion.matches) photo.animate([{opacity:.25,transform:'scale(1.035)'},{opacity:1,transform:'scale(1)'}],{duration:700,easing:'ease-out'});
}
const palette=[...new Map(products.map(p=>[p.color,p.swatch])).entries()];
$('#color-options').innerHTML=palette.map(([color,swatch])=>'<label class="color-choice"><input type="checkbox" data-color value="'+color+'"><span style="--swatch:'+swatch+'"></span>'+color+'</label>').join('');
const revealNodes=document.querySelectorAll('.section-heading,.details,.service-step,.store-main,.store-info,.bags-story');
if('IntersectionObserver' in window && !reducedMotion.matches){
  const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('is-visible');observer.unobserve(entry.target);}}),{threshold:.12});
  revealNodes.forEach(el=>{el.classList.add('reveal-ready');observer.observe(el);});
}
let scrollQueued=false;
function updateScroll(){
  const distance=document.documentElement.scrollHeight-innerHeight;
  $('.reading-progress').style.transform='scaleX('+(distance>0?scrollY/distance:0)+')';
  document.querySelector('header').classList.toggle('scrolled',scrollY>30);
  if(!reducedMotion.matches && innerWidth>800) $('.hero-photo img').style.translate='0 '+Math.min(scrollY*.07,38)+'px';
  scrollQueued=false;
}
window.addEventListener('scroll',()=>{if(!scrollQueued){scrollQueued=true;requestAnimationFrame(updateScroll);}},{passive:true});
updateScroll();
$('#year').textContent = new Date().getFullYear();
renderProducts();


