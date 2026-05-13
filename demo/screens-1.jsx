/* global React, Icons, useNav, useTheme, FileThumb, DEMO_FILES, DEMO_GALLERY, DEMO_USERS */
const { useState: useStateP, useEffect: useEffectP, useRef: useRefP, useMemo: useMemoP } = React;

/* ============================================================
   Login screen
   ============================================================ */
const LoginScreen = ({ onLogin }) => {
  const [email, setEmail] = useStateP("ronan@home.lan");
  const [pw, setPw] = useStateP("");
  return (
    <div style={{position:'relative', height:'100%', width:'100%', overflow:'hidden'}}>
      <div className="hc-ambient"><div className="orb"/><div className="grain"/></div>

      {/* top brand bar */}
      <div style={{position:'absolute', top:0, left:0, right:0, padding:'24px 32px', display:'flex', alignItems:'center', justifyContent:'space-between', zIndex:2}}>
        <div className="row gap-12">
          <div className="sb-brand-icon" style={{width:32, height:32, borderRadius:10}}>
            <Icons.cloud size={18} sw={2} />
          </div>
          <div>
            <div style={{fontWeight:600, fontSize:15}}>HomeCloud</div>
            <div style={{fontSize:11, color:'var(--hc-text-3)'}}>Auto-hébergé · Open source</div>
          </div>
        </div>
        <div className="row gap-12" style={{fontSize:13, color:'var(--hc-text-2)'}}>
          <a className="row gap-12" style={{padding:'6px 12px', borderRadius:8, cursor:'pointer'}}>
            <Icons.globe size={14} /> <span>FR</span>
          </a>
          <a className="row gap-12" style={{padding:'6px 12px', borderRadius:8, cursor:'pointer'}}>
            <Icons.info size={14} /> <span>Documentation</span>
          </a>
        </div>
      </div>

      <div style={{
        position:'relative', zIndex:1, height:'100%',
        display:'grid', placeItems:'center', padding:'24px',
      }}>
        <div className="glass-strong" style={{width:420, padding:32, borderRadius:20}}>
          <div style={{textAlign:'center', marginBottom:24}}>
            <div className="sb-brand-icon" style={{width:52, height:52, borderRadius:16, margin:'0 auto 14px'}}>
              <Icons.cloud size={26} sw={2} />
            </div>
            <h1 style={{fontSize:22, fontWeight:600, margin:'0 0 4px', letterSpacing:'-0.02em'}}>Bienvenue</h1>
            <p className="muted" style={{margin:0, fontSize:13.5}}>Connectez-vous à votre HomeCloud</p>
          </div>

          <label style={{fontSize:11.5, fontWeight:600, color:'var(--hc-text-2)', textTransform:'uppercase', letterSpacing:'0.06em', marginBottom:6, display:'block'}}>Adresse e-mail</label>
          <div style={{position:'relative', marginBottom:14}}>
            <input className="input" placeholder="vous@example.com" value={email} onChange={e=>setEmail(e.target.value)} style={{paddingLeft:38}} />
            <div style={{position:'absolute', left:12, top:'50%', transform:'translateY(-50%)', color:'var(--hc-text-3)'}}>
              <Icons.users size={15} />
            </div>
          </div>

          <label style={{fontSize:11.5, fontWeight:600, color:'var(--hc-text-2)', textTransform:'uppercase', letterSpacing:'0.06em', marginBottom:6, display:'block'}}>Mot de passe</label>
          <div style={{position:'relative', marginBottom:8}}>
            <input className="input" type="password" placeholder="••••••••" value={pw} onChange={e=>setPw(e.target.value)} style={{paddingLeft:38, paddingRight:38}} />
            <div style={{position:'absolute', left:12, top:'50%', transform:'translateY(-50%)', color:'var(--hc-text-3)'}}>
              <Icons.lock size={15} />
            </div>
            <div style={{position:'absolute', right:8, top:'50%', transform:'translateY(-50%)'}}>
              <button className="btn btn-ghost btn-icon"><Icons.eye size={15} /></button>
            </div>
          </div>

          <div className="row between" style={{marginBottom:18, fontSize:12.5}}>
            <label className="row" style={{gap:6, cursor:'pointer'}}>
              <input type="checkbox" defaultChecked style={{accentColor:'var(--hc-accent)'}} />
              <span>Se souvenir</span>
            </label>
            <a style={{color:'var(--hc-accent)', cursor:'pointer'}}>Mot de passe oublié ?</a>
          </div>

          <button className="btn btn-primary btn-lg" style={{width:'100%'}} onClick={onLogin}>
            Se connecter <Icons.arrowR size={15} />
          </button>

          <div className="row gap-12" style={{margin:'18px 0', alignItems:'center'}}>
            <div style={{height:1, flex:1, background:'var(--hc-border)'}} />
            <span style={{fontSize:11, color:'var(--hc-text-3)', textTransform:'uppercase', letterSpacing:'0.08em'}}>ou</span>
            <div style={{height:1, flex:1, background:'var(--hc-border)'}} />
          </div>

          <button className="btn btn-soft" style={{width:'100%', justifyContent:'center'}}>
            <Icons.key size={15} /> Connexion par clé Passkey
          </button>

          <div style={{textAlign:'center', marginTop:20, fontSize:12.5, color:'var(--hc-text-3)'}}>
            Pas de compte ? <a style={{color:'var(--hc-accent)', cursor:'pointer'}}>Demander un accès</a>
          </div>
        </div>
      </div>

      <div style={{position:'absolute', bottom:20, left:0, right:0, textAlign:'center', fontSize:11.5, color:'var(--hc-text-3)', zIndex:2}}>
        <span className="row gap-12" style={{justifyContent:'center', alignItems:'center'}}>
          <Icons.shield size={12} /> Connexion chiffrée · Données sur <span className="mono" style={{color:'var(--hc-text-2)'}}>192.168.1.42</span>
        </span>
      </div>
    </div>
  );
};

/* ============================================================
   Dashboard
   ============================================================ */
const Sparkline = ({ data, peaks = [] }) => (
  <div className="spark">{data.map((h, i) => <span key={i} className={peaks.includes(i) ? 'hi' : ''} style={{height:`${h}%`}} />)}</div>
);

const DashboardScreen = ({ onOpenFiles, onOpenGallery, onOpenShares }) => {
  const stats = [
    { label: "Stockage utilisé", value: "1.36 To", meta: "sur 2 To · 68%", icon: <Icons.hdd size={14} /> },
    { label: "Fichiers", value: "12 488", meta: <><span className="up">+128</span> cette semaine</>, icon: <Icons.folder size={14} /> },
    { label: "Partages actifs", value: "8", meta: "3 avec mot de passe", icon: <Icons.share size={14} /> },
    { label: "Charge serveur", value: "12%", meta: "CPU · RAM 2.1/8 Go", icon: <Icons.cpu size={14} /> },
  ];
  const recents = DEMO_FILES.slice(3, 8);
  const sharedSpark = [30, 45, 38, 60, 55, 80, 70, 90, 75, 88, 95, 82];

  return (
    <>
      <div className="page-header">
        <div>
          <h1 className="page-title">Bonjour Ronan 👋</h1>
          <p className="page-sub">Voici l'état de votre cloud — tout fonctionne normalement.</p>
        </div>
        <div className="page-actions">
          <button className="btn btn-soft"><Icons.upload size={15} /> Importer</button>
          <button className="btn btn-primary"><Icons.plus size={15} sw={2.2}/> Nouveau dossier</button>
        </div>
      </div>

      <div className="stat-grid">
        {stats.map((s, i) => (
          <div key={i} className="stat">
            <div className="stat-label">{s.icon} {s.label}</div>
            <div className="stat-value mono">{s.value}</div>
            <div className="stat-meta">{s.meta}</div>
          </div>
        ))}
      </div>

      <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap:14, marginBottom:20}}>
        <div className="glass" style={{padding:20}}>
          <div className="row between" style={{marginBottom:12}}>
            <div>
              <div style={{fontSize:14, fontWeight:600, marginBottom:2}}>Activité de partage</div>
              <div className="muted" style={{fontSize:12.5}}>12 derniers jours</div>
            </div>
            <button className="btn btn-ghost" style={{fontSize:12.5}}>Voir détails <Icons.chevR size={13} /></button>
          </div>
          <Sparkline data={sharedSpark} peaks={[5,7,10]} />
          <div className="row gap-16" style={{marginTop:14, fontSize:12.5}}>
            <div className="row gap-12"><span style={{width:8, height:8, borderRadius:'50%', background:'var(--hc-accent)'}}/> <span>Téléchargements <strong className="mono">142</strong></span></div>
            <div className="row gap-12"><span style={{width:8, height:8, borderRadius:'50%', background:'var(--hc-accent-2)'}}/> <span>Liens créés <strong className="mono">8</strong></span></div>
          </div>
        </div>

        <div className="glass" style={{padding:20}}>
          <div className="row between" style={{marginBottom:14}}>
            <div style={{fontSize:14, fontWeight:600}}>Famille connectée</div>
            <button className="btn btn-ghost" style={{fontSize:12.5}}>Gérer <Icons.chevR size={13} /></button>
          </div>
          <div className="col" style={{gap:10}}>
            {DEMO_USERS.map(u => (
              <div key={u.id} className="row between" style={{padding:'4px 0'}}>
                <div className="row gap-12">
                  <div style={{width:32, height:32, borderRadius:'50%', background:u.color, color:'white', display:'grid', placeItems:'center', fontSize:13, fontWeight:600}}>{u.initials}</div>
                  <div>
                    <div style={{fontSize:13.5, fontWeight:500}}>{u.name}</div>
                    <div className="muted-2" style={{fontSize:11.5}}>{u.email}</div>
                  </div>
                </div>
                <div className="row gap-12">
                  <span className="chip">{u.role}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      <div className="row between" style={{marginBottom:12, marginTop:8}}>
        <h2 style={{fontSize:16, fontWeight:600, margin:0, letterSpacing:'-0.01em'}}>Récents</h2>
        <button className="btn btn-ghost" style={{fontSize:12.5}} onClick={onOpenFiles}>Voir tout <Icons.chevR size={13} /></button>
      </div>

      <table className="file-table">
        <thead><tr><th>Nom</th><th>Modifié</th><th>Taille</th><th>Statut</th><th></th></tr></thead>
        <tbody>
          {recents.map(f => (
            <tr key={f.id}>
              <td>
                <div className="file-name">
                  <FileThumb file={f} />
                  <span style={{fontWeight:500}}>{f.name}</span>
                </div>
              </td>
              <td className="file-meta mono">{f.modified}</td>
              <td className="file-meta mono">{f.size}</td>
              <td>{f.shared && <span className="chip chip-shared"><Icons.share size={11} /> Partagé</span>}{f.public && <span className="chip chip-public"><Icons.link size={11} /> Public</span>}</td>
              <td><div className="file-actions"><button className="btn btn-ghost btn-icon"><Icons.more size={15}/></button></div></td>
            </tr>
          ))}
        </tbody>
      </table>
    </>
  );
};

window.LoginScreen = LoginScreen;
window.DashboardScreen = DashboardScreen;
