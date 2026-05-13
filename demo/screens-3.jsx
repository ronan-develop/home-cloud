/* global React, Icons, useNav, FileThumb, DEMO_FILES, DEMO_GALLERY, DEMO_USERS */
const { useState: useS3, useEffect: useE3, useRef: useR3 } = React;

/* ============================================================
   Photo Lightbox / File preview modal
   ============================================================ */
const Lightbox = ({ photo, onClose }) => {
  useE3(() => {
    const h = (e) => { if (e.key === 'Escape') onClose(); };
    window.addEventListener('keydown', h);
    return () => window.removeEventListener('keydown', h);
  }, [onClose]);
  return (
    <div className="modal-bg" onClick={onClose}>
      <div className="lightbox" onClick={e=>e.stopPropagation()}>
        <div className="lb-canvas">
          <button className="lb-close" onClick={onClose}><Icons.x size={16}/></button>
          <img src={photo.src.replace('w=600','w=1600')} alt="" />
        </div>
        <div className="lb-side">
          <div>
            <div style={{fontSize:15, fontWeight:600, marginBottom:2}}>{photo.name}</div>
            <div className="muted" style={{fontSize:12.5}}>Aujourd'hui · 14:32</div>
          </div>
          <div className="row gap-12">
            <button className="btn btn-soft" style={{flex:1, justifyContent:'center'}}><Icons.share size={14}/> Partager</button>
            <button className="btn btn-soft" style={{flex:1, justifyContent:'center'}}><Icons.download size={14}/></button>
            <button className="btn btn-soft btn-icon"><Icons.star size={14}/></button>
            <button className="btn btn-soft btn-icon"><Icons.trash size={14}/></button>
          </div>
          <div>
            <div className="sb-section-title" style={{padding:'4px 0'}}>Informations</div>
            <div className="rp-row"><span className="k">Type</span><span className="v">JPEG · 24-bit</span></div>
            <div className="rp-row"><span className="k">Dimensions</span><span className="v mono">4032 × 3024</span></div>
            <div className="rp-row"><span className="k">Taille</span><span className="v mono">3.4 Mo</span></div>
            <div className="rp-row"><span className="k">Appareil</span><span className="v">iPhone 15 Pro</span></div>
            <div className="rp-row"><span className="k">Lieu</span><span className="v">Heraklion, Crète</span></div>
          </div>
          <div>
            <div className="sb-section-title" style={{padding:'4px 0'}}>EXIF</div>
            <div className="rp-row"><span className="k">Ouverture</span><span className="v mono">f/1.78</span></div>
            <div className="rp-row"><span className="k">Vitesse</span><span className="v mono">1/240 s</span></div>
            <div className="rp-row"><span className="k">ISO</span><span className="v mono">125</span></div>
            <div className="rp-row"><span className="k">Focale</span><span className="v mono">24 mm</span></div>
          </div>
          <div>
            <div className="sb-section-title" style={{padding:'4px 0'}}>Albums</div>
            <div className="row gap-12" style={{flexWrap:'wrap'}}>
              <span className="chip">Crète 2025</span>
              <span className="chip">Vacances</span>
              <span className="chip">Été</span>
              <span className="chip" style={{cursor:'pointer'}}><Icons.plus size={11}/> Ajouter</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

/* ============================================================
   Share modal
   ============================================================ */
const ShareModal = ({ file, onClose }) => {
  const [mode, setMode] = useS3('private');
  const [pw, setPw] = useS3(false);
  return (
    <div className="modal-bg" onClick={onClose}>
      <div className="glass-strong" style={{width:480, borderRadius:18, padding:24}} onClick={e=>e.stopPropagation()}>
        <div className="row between" style={{marginBottom:16}}>
          <div>
            <div style={{fontSize:16, fontWeight:600, letterSpacing:'-0.01em'}}>Partager</div>
            <div className="muted" style={{fontSize:12.5}}>{file.name}</div>
          </div>
          <button className="btn btn-ghost btn-icon" onClick={onClose}><Icons.x size={16}/></button>
        </div>

        <div className="row" style={{padding:3, background:'var(--hc-surface-2)', borderRadius:10, border:'1px solid var(--hc-border)', marginBottom:16}}>
          {[
            {id:'private', label:'Avec la famille', icon:<Icons.users size={13}/>},
            {id:'link', label:'Lien public', icon:<Icons.link size={13}/>},
            {id:'email', label:'Par e-mail', icon:<Icons.share size={13}/>},
          ].map(m=>(
            <button key={m.id}
              onClick={()=>setMode(m.id)}
              className={`btn ${mode===m.id?'btn-soft':'btn-ghost'}`}
              style={{flex:1, justifyContent:'center', borderRadius:8, fontSize:12.5}}>
              {m.icon} {m.label}
            </button>
          ))}
        </div>

        {mode === 'private' && (
          <div>
            <label style={{fontSize:11.5, fontWeight:600, color:'var(--hc-text-2)', textTransform:'uppercase', letterSpacing:'0.06em', marginBottom:8, display:'block'}}>Inviter</label>
            <div className="col" style={{gap:6}}>
              {DEMO_USERS.slice(1).map(u=>(
                <div key={u.id} className="row between" style={{padding:'8px 10px', borderRadius:8, background:'var(--hc-surface-2)'}}>
                  <div className="row gap-12">
                    <div style={{width:28, height:28, borderRadius:'50%', background:u.color, color:'white', display:'grid', placeItems:'center', fontSize:11, fontWeight:600}}>{u.initials}</div>
                    <div>
                      <div style={{fontSize:13, fontWeight:500}}>{u.name}</div>
                      <div className="muted-2" style={{fontSize:11}}>{u.email}</div>
                    </div>
                  </div>
                  <select className="input" style={{width:120, padding:'6px 10px', fontSize:12.5}}>
                    <option>Voir</option>
                    <option>Modifier</option>
                  </select>
                </div>
              ))}
            </div>
          </div>
        )}

        {mode === 'link' && (
          <>
            <label style={{fontSize:11.5, fontWeight:600, color:'var(--hc-text-2)', textTransform:'uppercase', letterSpacing:'0.06em', marginBottom:8, display:'block'}}>Lien public</label>
            <div className="row gap-12" style={{marginBottom:14}}>
              <div className="input mono" style={{fontSize:12.5, color:'var(--hc-text-2)'}}>https://home.lan/s/k7Hf9pQ2x</div>
              <button className="btn btn-soft btn-icon"><Icons.copy size={14}/></button>
              <button className="btn btn-soft btn-icon"><Icons.qr size={14}/></button>
            </div>
            <div className="col" style={{gap:8}}>
              <Toggle label="Protection par mot de passe" sub="Demande un mot de passe avant ouverture" on={pw} onChange={setPw}/>
              <Toggle label="Date d'expiration" sub="Le lien expirera dans 7 jours" on={true} onChange={()=>{}}/>
              <Toggle label="Autoriser le téléchargement" sub="Les visiteurs peuvent télécharger l'original" on={true} onChange={()=>{}}/>
            </div>
          </>
        )}

        {mode === 'email' && (
          <div className="col" style={{gap:10}}>
            <input className="input" placeholder="adresse@email.com"/>
            <textarea className="input" rows={3} placeholder="Message (optionnel)" style={{resize:'none', fontFamily:'inherit'}}/>
          </div>
        )}

        <div className="row gap-12" style={{marginTop:18, justifyContent:'flex-end'}}>
          <button className="btn btn-ghost" onClick={onClose}>Annuler</button>
          <button className="btn btn-primary">Partager</button>
        </div>
      </div>
    </div>
  );
};

const Toggle = ({label, sub, on, onChange}) => (
  <div className="row between" style={{padding:'10px 0'}}>
    <div>
      <div style={{fontSize:13, fontWeight:500}}>{label}</div>
      {sub && <div className="muted-2" style={{fontSize:11.5}}>{sub}</div>}
    </div>
    <button onClick={()=>onChange(!on)} style={{
      width:36, height:21, borderRadius:999, padding:2,
      background: on ? 'var(--hc-accent)' : 'var(--hc-border-strong)',
      transition:'background 0.15s', position:'relative',
    }}>
      <div style={{
        width:17, height:17, borderRadius:'50%', background:'white',
        transform:`translateX(${on?15:0}px)`, transition:'transform 0.15s',
        boxShadow:'0 1px 3px rgba(0,0,0,0.2)',
      }}/>
    </button>
  </div>
);

/* ============================================================
   Command Palette (Cmd+K)
   ============================================================ */
const CmdK = ({ onClose, goto }) => {
  const [q, setQ] = useS3('');
  const ref = useR3();
  useE3(() => { ref.current && ref.current.focus(); }, []);
  useE3(() => {
    const h = (e) => { if (e.key === 'Escape') onClose(); };
    window.addEventListener('keydown', h);
    return () => window.removeEventListener('keydown', h);
  }, [onClose]);

  const actions = [
    { type:'nav', id:'dashboard', label:'Aller au tableau de bord', icon:<Icons.home size={14}/> },
    { type:'nav', id:'files',     label:'Aller à mes fichiers', icon:<Icons.folder size={14}/> },
    { type:'nav', id:'gallery',   label:'Aller à la galerie', icon:<Icons.image size={14}/> },
    { type:'nav', id:'shares',    label:'Aller aux partages', icon:<Icons.share size={14}/> },
    { type:'nav', id:'settings',  label:'Aller aux paramètres', icon:<Icons.settings size={14}/> },
    { type:'act', id:'upload',    label:'Importer des fichiers', icon:<Icons.upload size={14}/> },
    { type:'act', id:'newfolder', label:'Nouveau dossier', icon:<Icons.folder size={14}/> },
    { type:'act', id:'newshare',  label:'Créer un lien de partage', icon:<Icons.link size={14}/> },
    { type:'act', id:'theme',     label:'Basculer thème clair / sombre', icon:<Icons.moon size={14}/> },
  ];
  const recents = DEMO_FILES.slice(0, 4);
  const filtered = q ? actions.filter(a => a.label.toLowerCase().includes(q.toLowerCase())) : actions;

  return (
    <div className="modal-bg" onClick={onClose}>
      <div className="cmdk" onClick={e=>e.stopPropagation()}>
        <div className="cmdk-input">
          <Icons.search size={17} style={{color:'var(--hc-text-3)'}}/>
          <input ref={ref} placeholder="Rechercher fichiers, actions, personnes…" value={q} onChange={e=>setQ(e.target.value)}/>
          <span className="kbd" style={{fontSize:11, padding:'2px 6px', background:'var(--hc-bg-2)', borderRadius:5, border:'1px solid var(--hc-border)', fontFamily:'Geist Mono'}}>esc</span>
        </div>
        <div className="cmdk-list">
          {!q && (
            <>
              <div className="cmdk-section-h">Récents</div>
              {recents.map(f => (
                <div key={f.id} className="cmdk-row" onClick={onClose}>
                  <FileThumb file={f} size={26}/>
                  <span style={{flex:1}}>{f.name}</span>
                  <span className="muted-2 mono" style={{fontSize:11.5}}>{f.size}</span>
                </div>
              ))}
            </>
          )}
          <div className="cmdk-section-h">Navigation</div>
          {filtered.filter(a=>a.type==='nav').map(a => (
            <div key={a.id} className="cmdk-row" onClick={()=>{ goto(a.id); onClose(); }}>
              <div className="ico-wrap">{a.icon}</div>
              <span style={{flex:1}}>{a.label}</span>
            </div>
          ))}
          <div className="cmdk-section-h">Actions</div>
          {filtered.filter(a=>a.type==='act').map(a => (
            <div key={a.id} className="cmdk-row" onClick={onClose}>
              <div className="ico-wrap">{a.icon}</div>
              <span style={{flex:1}}>{a.label}</span>
              <span className="muted-2 mono" style={{fontSize:11.5}}>↵</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

/* ============================================================
   Drop overlay
   ============================================================ */
const DropOverlay = () => (
  <div className="drop-overlay">
    <div className="drop-overlay-inner">
      <div className="ico-big"><Icons.upload size={28} sw={2}/></div>
      <div style={{fontSize:18, fontWeight:600}}>Déposez pour importer</div>
      <div className="muted" style={{marginTop:4}}>Les fichiers seront ajoutés au dossier courant</div>
    </div>
  </div>
);

/* ============================================================
   Mobile preview
   ============================================================ */
const MobileView = () => {
  const [tab, setTab] = useS3('files');
  return (
    <div style={{
      width: 320, height: 660, borderRadius: 44,
      background:'#0a0d16', padding: 8,
      boxShadow:'0 30px 60px -20px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.1) inset',
      position:'relative',
    }}>
      <div style={{
        width:'100%', height:'100%', borderRadius:36, overflow:'hidden',
        background:'var(--hc-bg)', position:'relative',
      }}>
        {/* Status bar */}
        <div style={{
          position:'absolute', top:0, left:0, right:0, padding:'14px 24px 6px',
          display:'flex', justifyContent:'space-between', fontSize:13, fontWeight:600, zIndex:5,
        }}>
          <span className="mono">9:41</span>
          <div className="row gap-12" style={{fontSize:11}}>
            <span>•••</span><span>📶</span><span>🔋</span>
          </div>
        </div>
        {/* Notch */}
        <div style={{
          position:'absolute', top:8, left:'50%', transform:'translateX(-50%)',
          width:90, height:24, background:'#0a0d16', borderRadius:14, zIndex:6,
        }}/>

        {/* Content */}
        <div style={{position:'absolute', top:50, left:0, right:0, bottom:64, overflow:'auto'}}>
          {tab === 'files' && <MobileFiles/>}
          {tab === 'gallery' && <MobileGallery/>}
          {tab === 'shares' && <MobileShares/>}
          {tab === 'settings' && <MobileSettings/>}
        </div>

        {/* FAB */}
        <button className="btn btn-primary" style={{
          position:'absolute', bottom:80, right:16, width:48, height:48, borderRadius:'50%', padding:0,
          boxShadow:'0 10px 24px -6px var(--hc-accent)', zIndex:5,
        }}>
          <Icons.plus size={20} sw={2.4}/>
        </button>

        {/* Tab bar */}
        <div className="glass-strong" style={{
          position:'absolute', bottom:0, left:0, right:0, height:64,
          borderRadius:'0 0 36px 36px', borderTop:'1px solid var(--hc-border)',
          display:'grid', gridTemplateColumns:'repeat(4, 1fr)', alignItems:'center', paddingBottom:8,
        }}>
          {[
            {id:'files', icon:<Icons.folder size={20}/>, label:'Fichiers'},
            {id:'gallery', icon:<Icons.image size={20}/>, label:'Photos'},
            {id:'shares', icon:<Icons.share size={20}/>, label:'Partages'},
            {id:'settings', icon:<Icons.settings size={20}/>, label:'Réglages'},
          ].map(t=>(
            <button key={t.id} onClick={()=>setTab(t.id)} style={{
              display:'flex', flexDirection:'column', alignItems:'center', gap:2,
              color: tab===t.id?'var(--hc-accent)':'var(--hc-text-3)',
              fontSize:10, fontWeight:500,
            }}>
              {t.icon}
              <span>{t.label}</span>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
};

const MobileFiles = () => (
  <div>
    <div style={{padding:'12px 16px 8px'}}>
      <div style={{fontSize:24, fontWeight:600, letterSpacing:'-0.02em'}}>Fichiers</div>
      <div className="searchbar" style={{minWidth:0, marginTop:10}}>
        <Icons.search size={14}/>
        <span style={{fontSize:13}}>Rechercher</span>
      </div>
    </div>
    <div style={{padding:'4px 0'}}>
      {DEMO_FILES.slice(0,7).map(f=>(
        <div key={f.id} className="mobile-card">
          <FileThumb file={f} size={42}/>
          <div style={{flex:1, minWidth:0}}>
            <div style={{fontSize:14, fontWeight:500, overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap'}}>{f.name}</div>
            <div className="muted-2" style={{fontSize:11.5}}>{f.size} · {f.modified}</div>
          </div>
          <Icons.chevR size={16} style={{color:'var(--hc-text-3)'}}/>
        </div>
      ))}
    </div>
  </div>
);

const MobileGallery = () => (
  <div>
    <div style={{padding:'12px 16px'}}>
      <div style={{fontSize:24, fontWeight:600, letterSpacing:'-0.02em'}}>Photos</div>
    </div>
    <div style={{display:'grid', gridTemplateColumns:'repeat(3, 1fr)', gap:2, padding:'0 2px'}}>
      {DEMO_GALLERY.slice(0,15).map((src,i)=>(
        <div key={i} style={{aspectRatio:'1', overflow:'hidden'}}>
          <img src={src} alt="" style={{width:'100%', height:'100%', objectFit:'cover'}}/>
        </div>
      ))}
    </div>
  </div>
);

const MobileShares = () => (
  <div>
    <div style={{padding:'12px 16px'}}>
      <div style={{fontSize:24, fontWeight:600, letterSpacing:'-0.02em'}}>Partages</div>
    </div>
    {[
      {n:"Photos famille / Été 2025", d:"Avec Émilie, Léa", views:142},
      {n:"Quittance loyer.pdf", d:"Lien public", views:8},
      {n:"Vacances Crète 2025", d:"mamie@gmail.com", views:24},
    ].map((s,i)=>(
      <div key={i} className="mobile-card">
        <div style={{width:42, height:42, borderRadius:11, background:'var(--hc-accent-soft)', color:'var(--hc-accent)', display:'grid', placeItems:'center'}}>
          <Icons.link size={18}/>
        </div>
        <div style={{flex:1, minWidth:0}}>
          <div style={{fontSize:14, fontWeight:500}}>{s.n}</div>
          <div className="muted-2" style={{fontSize:11.5}}>{s.d} · {s.views} vues</div>
        </div>
        <Icons.chevR size={16} style={{color:'var(--hc-text-3)'}}/>
      </div>
    ))}
  </div>
);

const MobileSettings = () => (
  <div>
    <div style={{padding:'12px 16px'}}>
      <div style={{fontSize:24, fontWeight:600, letterSpacing:'-0.02em'}}>Réglages</div>
    </div>
    <div style={{padding:'16px', textAlign:'center'}}>
      <div style={{width:72, height:72, borderRadius:'50%', background:'linear-gradient(135deg, #2b5fff, #6e8bff)', color:'white', display:'grid', placeItems:'center', margin:'0 auto', fontSize:28, fontWeight:600}}>R</div>
      <div style={{marginTop:10, fontWeight:600}}>Ronan</div>
      <div className="muted-2" style={{fontSize:12}}>ronan@home.lan · Admin</div>
    </div>
    {[
      {i:<Icons.users size={17}/>, l:"Compte"},
      {i:<Icons.shield size={17}/>, l:"Sécurité"},
      {i:<Icons.hdd size={17}/>, l:"Stockage"},
      {i:<Icons.bell size={17}/>, l:"Notifications"},
      {i:<Icons.moon size={17}/>, l:"Apparence"},
    ].map((s,i)=>(
      <div key={i} className="mobile-card">
        <div style={{width:36, height:36, borderRadius:9, background:'var(--hc-surface-2)', color:'var(--hc-text-2)', display:'grid', placeItems:'center'}}>{s.i}</div>
        <div style={{flex:1, fontSize:14, fontWeight:500}}>{s.l}</div>
        <Icons.chevR size={16} style={{color:'var(--hc-text-3)'}}/>
      </div>
    ))}
  </div>
);

window.Lightbox = Lightbox;
window.ShareModal = ShareModal;
window.CmdK = CmdK;
window.DropOverlay = DropOverlay;
window.MobileView = MobileView;
