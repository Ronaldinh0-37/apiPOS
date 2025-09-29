//  DEPARTMENT, PROVINCE, DISTRICT
document.addEventListener('DOMContentLoaded', () => {

  //  SELECT ELEMENTS
  const selDepa = document.getElementById('departamento');
  const selProv = document.getElementById('provincia');
  const selDist = document.getElementById('distrito');

  //  CHECK IF SELECTS EXIST
  if (!selDepa || !selProv || !selDist) {
    console.warn('register_ubigeo.js: uno o más selects no encontrados (esperados: departamento, provincia, distrito).');
    return;
  }

  //  JSON FILE PATHS
  const basePath = 'json/'; 
  const depFile = basePath + 'peru_departamentos.json';
  const provFile = basePath + 'peru_provincias.json';
  const distFile = basePath + 'peru_distritos.json';

  //  INITIAL SELECT MESSAGES
  selDepa.innerHTML = `<option value="" disabled selected>Cargando departamentos...</option>`;
  selProv.innerHTML = `<option value="" disabled selected>Provincia</option>`;
  selDist.innerHTML = `<option value="" disabled selected>Distrito</option>`;

  //  LOAD JSON DATA FOR DEPARTMENTS, PROVINCES, DISTRICTS
  Promise.all([
    fetch(depFile).then(r => {
      if (!r.ok) throw new Error('dep fetch ' + r.status);
      return r.json();
    }),
    fetch(provFile).then(r => {
      if (!r.ok) throw new Error('prov fetch ' + r.status);
      return r.json();
    }),
    fetch(distFile).then(r => {
      if (!r.ok) throw new Error('dist fetch ' + r.status);
      return r.json();
    })
  ])
  .then(([depJson, provJson, distJson]) => {

    //  NORMALIZE ARRAYS
    const departamentos = Array.isArray(depJson.rows) ? depJson.rows : (Array.isArray(depJson) ? depJson : []);
    const provincias = Array.isArray(provJson.rows) ? provJson.rows : (Array.isArray(provJson) ? provJson : []);
    const distritos = Array.isArray(distJson.rows) ? distJson.rows : (Array.isArray(distJson) ? distJson : []);

    //  NO DEPARTMENTS FOUND
    if (departamentos.length === 0) {
      console.warn('register_ubigeo.js: no se encontraron departamentos en el JSON.');
      selDepa.innerHTML = `<option value="" disabled selected>No hay departamentos</option>`;
      return;
    }

    //  POPULATE DEPARTMENTS SELECT
    selDepa.innerHTML = `<option value="" disabled selected>Departamento</option>`;
    departamentos.forEach(d => {
      //  ENSURE ID AND NAME EXIST
      const id = d.id ?? d.codigo ?? d.code ?? d.value ?? '';
      const name = d.name ?? d.nombre ?? d.label ?? '';
      if (id && name) selDepa.insertAdjacentHTML('beforeend', `<option value="${id}">${name}</option>`);
    });

    //  STORE PROVINCES AND DISTRICTS ARRAYS IN DATASET
    selDepa.dataset.provincias = JSON.stringify(provincias);
    selDepa.dataset.distritos = JSON.stringify(distritos);

  })
  .catch(err => {
    //  ERROR LOADING JSON FILES
    console.error('register_ubigeo.js - error cargando JSONs:', err);
    selDepa.innerHTML = `<option value="" disabled selected>Error cargando datos</option>`;
  });

  //  ON DEPARTMENT CHANGE => POPULATE PROVINCES
  selDepa.addEventListener('change', () => {
    const depId = selDepa.value;
    selProv.innerHTML = `<option value="" disabled selected>Provincia</option>`;
    selDist.innerHTML = `<option value="" disabled selected>Distrito</option>`;

    let provincias = [];
    try { provincias = JSON.parse(selDepa.dataset.provincias || '[]'); } catch(e) { provincias = []; }

    provincias
      .filter(p => String(p.department_id ?? p.departamento_id ?? p.dep_id ?? '') === String(depId))
      .forEach(p => {
        const id = p.id ?? p.codigo ?? p.code ?? p.value ?? '';
        const name = p.name ?? p.nombre ?? p.label ?? '';
        if (id && name) selProv.insertAdjacentHTML('beforeend', `<option value="${id}">${name}</option>`);
      });

    //  IF NO PROVINCES FOUND
    if (selProv.options.length === 1) {
      selProv.innerHTML = `<option value="" disabled selected>Sin provincias</option>`;
    }
  });

  //  ON PROVINCE CHANGE => POPULATE DISTRICTS
  selProv.addEventListener('change', () => {
    const provId = selProv.value;
    selDist.innerHTML = `<option value="" disabled selected>Distrito</option>`;

    let distritos = [];
    try { distritos = JSON.parse(selDepa.dataset.distritos || '[]'); } catch(e) { distritos = []; }

    distritos
      .filter(d => String(d.province_id ?? d.provincia_id ?? d.prov_id ?? '') === String(provId))
      .forEach(d => {
        const id = d.id ?? d.codigo ?? d.code ?? d.value ?? '';
        const name = d.name ?? d.nombre ?? d.label ?? '';
        if (id && name) selDist.insertAdjacentHTML('beforeend', `<option value="${id}">${name}</option>`);
      });

    //  IF NO DISTRICTS FOUND
    if (selDist.options.length === 1) {
      selDist.innerHTML = `<option value="" disabled selected>Sin distritos</option>`;
    }
  });

});
