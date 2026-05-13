/* global React, Icons, useNav, FileThumb, DEMO_FILES, DEMO_GALLERY */
const { useState: useS2, useEffect: useE2, useMemo: useM2, useRef: useR2 } = React;

/* ============================================================
   Files screen (explorer)
   ============================================================ */
const FilesScreen = ({ onOpenFile, onShare }) => {
  const [view, setView] = useS2("list");
  const [selected, setSelected] = useS2(new Set());
  const [sortBy, setSortBy] = useS2("modified");

  const toggle = (id) => {
    setSelected(prev => {
      const n = new Set(prev);
      n.has(id) ? n.delete(id) : n.add(id);
      return n;
    });
  };

  return (
    <>
      <div className="page-header">
        <div>
          <h1 className="page-title">Mes fichiers</h1>
          <p className="page-sub">12 488 fichiers · 1.36 To utilisés</p>
        </div>
        <div className="page-actions">
          <button className="btn btn-soft"><Icons.upload size={15} /> Importer</button>
          <button className="btn btn-soft"><Icons.folder size={15} /> Nouveau dossier</button>
          <button className="btn btn-primary"><Icons.plus size={15} sw={2.2}/> Nouveau</button>
        </div>
      </div>

      <div className="row between" style={{marginBottom:14}}>
        <div className="row gap-12">
          <button className="btn btn-soft"><Icons.filter size={14} /> Filtrer</button>
          <button className="btn btn-soft"><Icons.sort size={14} /> Trier · {sortBy === 'modified' ? 'Récent' : 'Nom'}</button>
          {selected.size > 0 && (
            <>
              <div style={{width:1, height:20, background:'var(--hc-border)'}}/>
              <span className="muted" style={{fontSize:13}}>{selected.size} sélectionné{selected.size>1?'s':''}</span>
              <button className="btn btn-ghost" style={{color:'var(--hc-accent)'}}><Icons.share size={14} /> Partager</button>
              <button className="btn btn-ghost"><Icons.download size={14} /> Télécharger</button>
              <button className="btn btn-ghost" style={{color:'var(--hc-status-err)'}}><Icons.trash size={14} /> Supprimer</button>
            </>
          )}
        </div>
        <div className="row gap-12">
          <div className="row" style={{padding:2, background:'var(--hc-surface-2)', border:'1px solid var(--hc-border)', borderRadius:8}}>
            <button className={`btn btn-icon ${view==='list'?'btn-soft':'btn-ghost'}`} style={{borderRadius:6, height:28, width:28}} onClick={()=>setView('list')}><Icons.list size={14}/></button>
            <button className={`btn btn-icon ${view==='grid'?'btn-soft':'btn-ghost'}`} style={{borderRadius:6, height:28, width:28}} onClick={()=>setView('grid')}><Icons.grid size={14}/></button>
          </div>
        </div>
      </div>

      {view === 'list' ? (
        <table className="file-table">
          <thead>
            <tr>
              <th style={{width:36}}><input type="checkbox" style={{accentColor:'var(--hc-accent)'}} /></th>
              <th>Nom</th>
              <th>Modifié</th>
              <th>Taille</th>
              <th>Statut</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {DEMO_FILES.map(f => (
              <tr key={f.id} className={selected.has(f.id)?'selected':''} onClick={()=>onOpenFile(f)}>
                <td onClick={e=>{e.stopPropagation(); toggle(f.id);}}>
                  <input type="checkbox" checked={selected.has(f.id)} readOnly style={{accentColor:'var(--hc-accent)'}} />
                </td>
                <td>
                  <div className="file-name">
                    <FileThumb file={f} />
                    <div>
                      <div style={{fontWeight:500}}>{f.name}</div>
                      {f.count && <div className="muted-2" style={{fontSize:11.5}}>{f.count}</div>}
                    </div>
                  </div>
                </td>
                <td className="file-meta mono">{f.modified}</td>
                <td className="file-meta mono">{f.size}</td>
                <td>
                  {f.shared && <span className="chip chip-shared"><Icons.share size={11} /> Partagé</span>}
                  {f.public && <span className="chip chip-public"><Icons.link size={11} /> Lien public</span>}
                </td>
                <td>
                  <div className="file-actions" onClick={e=>e.stopPropagation()}>
                    <button className="btn btn-ghost btn-icon" onClick={()=>onShare(f)}><Icons.share size={14}/></button>
                    <button className="btn btn-ghost btn-icon"><Icons.download size={14}/></button>
                    <button className="btn btn-ghost btn-icon"><Icons.more size={15}/></button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      ) : (
        <div style={{display:'grid', gridTemplateColumns:'repeat(auto-fill, minmax(180px, 1fr))', gap:14}}>
          {DEMO_FILES.map(f => (
            <div key={f.id} className="glass" style={{padding:14, cursor:'pointer'}} onClick={()=>onOpenFile(f)}>
              <FileThumb file={f} size={56} />
              <div style={{marginTop:10, fontSize:13.5, fontWeight:500, overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap'}}>{f.name}</div>
              <div className="muted-2" style={{fontSize:11.5, marginTop:2}} className="mono">{f.size}</div>
            </div>
          ))}
        </div>
      )}
    </>
  );
};

/* ============================================================
   Gallery screen
   ============================================================ */
const GalleryScreen = ({ onOpenPhoto }) => {
  const [selected, setSelected] = useS2(new Set());
  const groups = [
    { title: "Aujourd'hui", sub: "Crète · 14h32", items: DEMO_GALLERY.slice(0, 6) },
    { title: "Cette semaine", sub: "Maison · jardin", items: DEMO_GALLERY.slice(6, 12) },
    { title: "Avril 2026", sub: "Souvenirs", items: DEMO_GALLERY.slice(12, 16) },
  ];

  return (
    <>
      <div className="page-header">
        <div>
          <h1 className="page-title">Galerie</h1>
          <p className="page-sub">1 248 photos · 86 vidéos · regroupés par moment</p>
        </div>
        <div className="page-actions">
          <button className="btn btn-soft"><Icons.filter size={15} /> Tous</button>
          <button className="btn btn-soft"><Icons.image size={15} /> Albums</button>
          <button className="btn btn-primary"><Icons.upload size={15}/> Importer</button>
        </div>
      </div>

      {groups.map((g, gi) => (
        <div key={gi}>
          <div className="gallery-section-title">
            <span>{g.title}</span>
            <span className="count">· {g.sub}</span>
            <span className="count">· {g.items.length} photos</span>
          </div>
          <div className="gallery-grid">
            {g.items.map((src, i) => {
              const id = `${gi}-${i}`;
              const isSel = selected.has(id);
              return (
                <div key={id} className={`gallery-tile ${isSel?'selected':''}`} onClick={(e)=>{
                  if (e.shiftKey || e.metaKey || e.ctrlKey) {
                    setSelected(prev => { const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n; });
                  } else {
                    onOpenPhoto({ src, name: `IMG_${4800+gi*10+i}.jpeg` });
                  }
                }}>
                  <img src={src} alt="" loading="lazy" />
                  <div className="check" onClick={(e)=>{
                    e.stopPropagation();
                    setSelected(prev => { const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n; });
                  }}>
                    {isSel && <Icons.check size={12} sw={3} style={{color:'white'}}/>}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      ))}
    </>
  );
};

/* ============================================================
   Shares screen
   ============================================================ */
const SharesScreen = () => {
  const shares = [
    { name: "Photos famille / Été 2025", recipients: ["Émilie", "Léa"], type: "private", views: 142, expires: "Dans 18 jours" },
    { name: "Quittance loyer avril.pdf", recipients: [], type: "public-pw", views: 8, expires: "Permanent" },
    { name: "Vacances Crète 2025", recipients: ["mamie@gmail.com"], type: "link", views: 24, expires: "Dans 3 jours" },
    { name: "Anniversaire Léa.mov", recipients: ["Toute la famille"], type: "private", views: 56, expires: "Permanent" },
  ];
  return (
    <>
      <div className="page-header">
        <div>
          <h1 className="page-title">Partages</h1>
          <p className="page-sub">8 liens actifs · 230 consultations ce mois-ci</p>
        </div>
        <div className="page-actions">
          <button className="btn btn-primary"><Icons.link size={15}/> Nouveau partage</button>
        </div>
      </div>
      <div className="col" style={{gap:10}}>
        {shares.map((s, i) => (
          <div key={i} className="glass" style={{padding:'14px 18px', display:'grid', gridTemplateColumns:'1fr auto auto auto auto', gap:24, alignItems:'center'}}>
            <div className="row gap-12">
              <div style={{width:36, height:36, borderRadius:9, background:'var(--hc-accent-soft)', color:'var(--hc-accent)', display:'grid', placeItems:'center'}}>
                <Icons.link size={16}/>
              </div>
              <div style={{minWidth:0}}>
                <div style={{fontSize:13.5, fontWeight:500}}>{s.name}</div>
                <div className="muted-2" style={{fontSize:11.5}}>{s.recipients.length ? `Avec ${s.recipients.join(', ')}` : 'Lien public'}</div>
              </div>
            </div>
            <div>
              {s.type === 'public-pw' && <span className="chip"><Icons.lock size={11}/> Mot de passe</span>}
              {s.type === 'private' && <span className="chip chip-shared"><Icons.users size={11}/> Privé</span>}
              {s.type === 'link' && <span className="chip chip-public"><Icons.globe size={11}/> Lien</span>}
            </div>
            <div className="muted mono" style={{fontSize:12.5}}>{s.views} vues</div>
            <div className="muted" style={{fontSize:12.5}}>{s.expires}</div>
            <div className="row gap-12">
              <button className="btn btn-ghost btn-icon"><Icons.copy size={14}/></button>
              <button className="btn btn-ghost btn-icon"><Icons.qr size={14}/></button>
              <button className="btn btn-ghost btn-icon"><Icons.more size={15}/></button>
            </div>
          </div>
        ))}
      </div>
    </>
  );
};

/* ============================================================
   Settings screen
   ============================================================ */
const SettingsScreen = () => {
  const [tab, setTab] = useS2("profile");
  const tabs = [
    { id: "profile", label: "Profil", icon: <Icons.users size={14}/> },
    { id: "security", label: "Sécurité", icon: <Icons.shield size={14}/> },
    { id: "storage", label: "Stockage", icon: <Icons.hdd size={14}/> },
    { id: "users", label: "Utilisateurs", icon: <Icons.users size={14}/> },
    { id: "server", label: "Serveur", icon: <Icons.cpu size={14}/> },
  ];
  return (
    <>
      <div className="page-header">
        <div>
          <h1 className="page-title">Paramètres</h1>
          <p className="page-sub">Gérez votre compte, votre famille et votre serveur</p>
        </div>
      </div>
      <div style={{display:'grid', gridTemplateColumns:'200px 1fr', gap:24}}>
        <div className="col" style={{gap:2}}>
          {tabs.map(t => (
            <div key={t.id} className={`sb-item ${tab===t.id?'active':''}`} onClick={()=>setTab(t.id)} style={{padding:'8px 12px'}}>
              {React.cloneElement(t.icon, {className:'ico'})}
              <span>{t.label}</span>
            </div>
          ))}
        </div>
        <div>
          {tab === 'profile' && <ProfilePanel/>}
          {tab === 'security' && <SecurityPanel/>}
          {tab === 'storage' && <StoragePanel/>}
          {tab === 'users' && <UsersPanel/>}
          {tab === 'server' && <ServerPanel/>}
        </div>
      </div>
    </>
  );
};

const ProfilePanel = () => (
  <div className="glass" style={{padding:24}}>
    <div className="row gap-16" style={{marginBottom:24}}>
      <div style={{width:64, height:64, borderRadius:16, background:'linear-gradient(135deg, #2b5fff, #6e8bff)', color:'white', display:'grid', placeItems:'center', fontSize:24, fontWeight:600}}>R</div>
      <div>
        <div style={{fontSize:18, fontWeight:600, letterSpacing:'-0.01em'}}>Ronan</div>
        <div className="muted" style={{fontSize:13}}>Administrateur · ronan@home.lan</div>
        <button className="btn btn-soft" style={{marginTop:8, fontSize:12}}><Icons.upload size={13}/> Changer la photo</button>
      </div>
    </div>
    <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap:16}}>
      <Field label="Nom complet" value="Ronan Le Berre"/>
      <Field label="Email" value="ronan@home.lan"/>
      <Field label="Langue" value="Français" select/>
      <Field label="Fuseau" value="Europe/Paris" select/>
    </div>
  </div>
);

const Field = ({label, value, select}) => (
  <div>
    <label style={{fontSize:11.5, fontWeight:600, color:'var(--hc-text-2)', textTransform:'uppercase', letterSpacing:'0.06em', marginBottom:6, display:'block'}}>{label}</label>
    <div className="input row between" style={{cursor:select?'pointer':'text'}}>
      <span>{value}</span>
      {select && <Icons.chevD size={14}/>}
    </div>
  </div>
);

const SecurityPanel = () => (
  <div className="col" style={{gap:14}}>
    <div className="glass" style={{padding:20}}>
      <div style={{fontSize:14, fontWeight:600, marginBottom:14}}>Authentification</div>
      <div className="row between" style={{padding:'10px 0', borderBottom:'1px solid var(--hc-border)'}}>
        <div>
          <div style={{fontSize:13.5, fontWeight:500}}>Mot de passe</div>
          <div className="muted-2" style={{fontSize:12}}>Modifié il y a 42 jours</div>
        </div>
        <button className="btn btn-soft">Modifier</button>
      </div>
      <div className="row between" style={{padding:'10px 0', borderBottom:'1px solid var(--hc-border)'}}>
        <div>
          <div style={{fontSize:13.5, fontWeight:500}}>Authentification à deux facteurs</div>
          <div className="muted-2" style={{fontSize:12}}>Application TOTP · activée</div>
        </div>
        <span className="chip chip-public"><Icons.check size={11} sw={3}/> Active</span>
      </div>
      <div className="row between" style={{padding:'10px 0'}}>
        <div>
          <div style={{fontSize:13.5, fontWeight:500}}>Clés Passkey</div>
          <div className="muted-2" style={{fontSize:12}}>2 clés enregistrées (iPhone, MacBook)</div>
        </div>
        <button className="btn btn-soft">Gérer</button>
      </div>
    </div>
    <div className="glass" style={{padding:20}}>
      <div style={{fontSize:14, fontWeight:600, marginBottom:14}}>Sessions actives</div>
      {[
        {dev:"MacBook Pro · Safari", loc:"Maison · 192.168.1.42", date:"Maintenant", curr:true},
        {dev:"iPhone 15 · App native", loc:"Maison · 192.168.1.88", date:"Il y a 2h", curr:false},
      ].map((s,i)=>(
        <div key={i} className="row between" style={{padding:'10px 0', borderTop:i?'1px solid var(--hc-border)':'none'}}>
          <div>
            <div style={{fontSize:13.5, fontWeight:500}}>{s.dev} {s.curr && <span className="chip" style={{marginLeft:8}}>Cet appareil</span>}</div>
            <div className="muted-2" style={{fontSize:12}}>{s.loc} · {s.date}</div>
          </div>
          {!s.curr && <button className="btn btn-ghost" style={{color:'var(--hc-status-err)'}}>Révoquer</button>}
        </div>
      ))}
    </div>
  </div>
);

const StoragePanel = () => {
  const segments = [
    { label: "Photos & vidéos", value: 820, color:"#2b5fff" },
    { label: "Documents", value: 286, color:"#10b981" },
    { label: "Sauvegardes", value: 184, color:"#f59e0b" },
    { label: "Audio", value: 72, color:"#ec4899" },
    { label: "Autres", value: 38, color:"#94a3b8" },
  ];
  const total = 2000;
  const used = segments.reduce((a,b)=>a+b.value,0);
  return (
    <div className="glass" style={{padding:20}}>
      <div className="row between" style={{marginBottom:6}}>
        <div style={{fontSize:14, fontWeight:600}}>Répartition</div>
        <div className="mono" style={{fontSize:13}}><strong>{(used/1000).toFixed(2)} To</strong> <span className="muted">/ {total/1000} To</span></div>
      </div>
      <div className="row" style={{height:14, borderRadius:7, overflow:'hidden', border:'1px solid var(--hc-border)', marginBottom:14}}>
        {segments.map((s,i)=> <div key={i} style={{width:`${s.value/total*100}%`, background:s.color}}/>)}
        <div style={{flex:1, background:'var(--hc-bg-2)'}}/>
      </div>
      <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap:10}}>
        {segments.map((s,i)=>(
          <div key={i} className="row between" style={{padding:'8px 12px', borderRadius:8, background:'var(--hc-surface-2)'}}>
            <div className="row gap-12">
              <span style={{width:10, height:10, borderRadius:3, background:s.color}}/>
              <span style={{fontSize:13}}>{s.label}</span>
            </div>
            <span className="mono" style={{fontSize:12.5, color:'var(--hc-text-2)'}}>{(s.value/1000).toFixed(2)} To</span>
          </div>
        ))}
      </div>
    </div>
  );
};

const UsersPanel = () => (
  <div className="glass" style={{padding:0, overflow:'hidden'}}>
    <div className="row between" style={{padding:'14px 20px', borderBottom:'1px solid var(--hc-border)'}}>
      <div style={{fontSize:14, fontWeight:600}}>Membres ({DEMO_USERS.length})</div>
      <button className="btn btn-primary"><Icons.plus size={14} sw={2.2}/> Inviter</button>
    </div>
    {DEMO_USERS.map((u,i)=>(
      <div key={u.id} className="row between" style={{padding:'14px 20px', borderTop:i?'1px solid var(--hc-border)':'none'}}>
        <div className="row gap-12">
          <div style={{width:36, height:36, borderRadius:'50%', background:u.color, color:'white', display:'grid', placeItems:'center', fontWeight:600}}>{u.initials}</div>
          <div>
            <div style={{fontSize:13.5, fontWeight:500}}>{u.name}</div>
            <div className="muted-2" style={{fontSize:12}}>{u.email}</div>
          </div>
        </div>
        <div className="row gap-12">
          <span className="chip">{u.role}</span>
          <span className="muted-2 mono" style={{fontSize:12}}>{i===0?'1.2 To':i===1?'420 Go':i===2?'180 Go':'92 Go'}</span>
          <button className="btn btn-ghost btn-icon"><Icons.more size={15}/></button>
        </div>
      </div>
    ))}
  </div>
);

const ServerPanel = () => (
  <div className="col" style={{gap:14}}>
    <div className="glass" style={{padding:20}}>
      <div style={{fontSize:14, fontWeight:600, marginBottom:14}}>Système</div>
      <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap:10, fontSize:13}}>
        <Row k="Hôte" v="home.lan" mono/>
        <Row k="IP locale" v="192.168.1.42" mono/>
        <Row k="Version" v="HomeCloud 1.4.2" mono/>
        <Row k="PHP" v="8.3.6" mono/>
        <Row k="OS" v="Debian 12.4"/>
        <Row k="Uptime" v="14j 6h 22min"/>
      </div>
    </div>
    <div className="glass" style={{padding:20}}>
      <div style={{fontSize:14, fontWeight:600, marginBottom:14}}>Mises à jour</div>
      <div className="row between">
        <div>
          <div style={{fontSize:13.5, fontWeight:500}}>Vous êtes à jour</div>
          <div className="muted-2" style={{fontSize:12}}>Dernière vérification : il y a 12 minutes</div>
        </div>
        <button className="btn btn-soft"><Icons.zap size={14}/> Vérifier</button>
      </div>
    </div>
  </div>
);

const Row = ({k, v, mono}) => (
  <div className="row between" style={{padding:'7px 0', borderBottom:'1px solid var(--hc-border)'}}>
    <span className="muted">{k}</span>
    <span className={mono?'mono':''} style={{fontWeight:500}}>{v}</span>
  </div>
);

window.FilesScreen = FilesScreen;
window.GalleryScreen = GalleryScreen;
window.SharesScreen = SharesScreen;
window.SettingsScreen = SettingsScreen;
