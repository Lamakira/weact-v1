/* fields.jsx — reusable form primitives (stateful for prototype feel). Exports to window. */
const { useState } = React;

function Field({ label, icon, defaultValue = '', type = 'text', placeholder = ' ', full }) {
  const [v, setV] = useState(defaultValue);
  return (
    <div className={'pf-field' + (full ? ' full' : '')} style={full ? { gridColumn: '1 / -1' } : {}}>
      <input type={type} value={v} placeholder={placeholder} onChange={(e) => setV(e.target.value)} />
      {icon && <Icon name={icon} size={17} className="lead" />}
      <label>{label}</label>
    </div>
  );
}

function SelectField({ label, icon, options, defaultValue, full }) {
  const [v, setV] = useState(defaultValue ?? options[0]);
  return (
    <div className={'pf-field sel' + (full ? ' full' : '')} style={full ? { gridColumn: '1 / -1' } : {}}>
      <select value={v} onChange={(e) => setV(e.target.value)}>
        {options.map((o) => <option key={o} value={o}>{o}</option>)}
      </select>
      {icon && <Icon name={icon} size={17} className="lead" />}
      <Icon name="chevron" size={17} className="chev" />
      <label>{label}</label>
    </div>
  );
}

function TextareaField({ label, icon, defaultValue = '', rows = 4, max = 500 }) {
  const [v, setV] = useState(defaultValue);
  return (
    <div className="pf-group">
      <div className="pf-field">
        <textarea rows={rows} maxLength={max} value={v} placeholder=" " onChange={(e) => setV(e.target.value)} />
        {icon && <Icon name={icon} size={17} className="lead" />}
        <label>{label}</label>
      </div>
      <div className="pf-help count">{v.length} / {max} caractères</div>
    </div>
  );
}

function ChipInput({ label, defaultChips = [], placeholder = 'Ajouter…' }) {
  const [chips, setChips] = useState(defaultChips);
  const [draft, setDraft] = useState('');
  const add = () => {
    const t = draft.trim();
    if (t && !chips.includes(t)) setChips([...chips, t]);
    setDraft('');
  };
  return (
    <div className="pf-group">
      <span className="glabel">{label}</span>
      <div className="pf-chipbox">
        {chips.map((c) => (
          <span key={c} className="pf-chip">
            {c}
            <button onClick={() => setChips(chips.filter((x) => x !== c))} aria-label={'Retirer ' + c}>
              <Icon name="x" size={13} />
            </button>
          </span>
        ))}
        <input className="pf-chip-input" value={draft} placeholder={chips.length ? '' : placeholder}
          onChange={(e) => setDraft(e.target.value)}
          onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); add(); } }} />
      </div>
    </div>
  );
}

function SelChips({ label, options, defaultSelected = [] }) {
  const [sel, setSel] = useState(defaultSelected);
  const toggle = (o) => setSel(sel.includes(o) ? sel.filter((x) => x !== o) : [...sel, o]);
  return (
    <div className="pf-group">
      <span className="glabel">{label}</span>
      <div className="pf-selgrid">
        {options.map((o) => {
          const on = sel.includes(o);
          return (
            <button key={o} className={'pf-selchip' + (on ? ' sel' : '')} onClick={() => toggle(o)}>
              {on && <span className="ck"><Icon name="check" size={14} /></span>}
              {o}
            </button>
          );
        })}
      </div>
    </div>
  );
}

function Toggle({ defaultOn = false }) {
  const [on, setOn] = useState(defaultOn);
  return (
    <button className={'pf-switch' + (on ? ' on' : '')} onClick={() => setOn(!on)} role="switch" aria-checked={on}>
      <i />
    </button>
  );
}

function SaveBar({ xp }) {
  const [state, setState] = useState('idle'); // idle | saving | saved
  const save = () => {
    setState('saving');
    setTimeout(() => {
      setState('saved');
      setTimeout(() => setState('idle'), 1900);
    }, 650);
  };
  return (
    <div className="pf-savebar">
      <div className={'meta' + (state === 'saved' ? ' saved' : '')}>
        {state === 'saved'
          ? (<><Icon name="check" size={15} /> Modifications enregistrées</>)
          : (<><Icon name="info" size={14} /> Pensez à enregistrer vos modifications</>)}
      </div>
      <button className="pf-btn pf-btn-ghost">Annuler</button>
      <button className="pf-btn pf-btn-primary" onClick={save} disabled={state === 'saving'}>
        {state === 'saving' ? 'Enregistrement…' : 'Enregistrer'}
      </button>
    </div>
  );
}

Object.assign(window, { Field, SelectField, TextareaField, ChipInput, SelChips, Toggle, SaveBar });
