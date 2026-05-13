/* global React */
// (hooks accessed via React.useState etc. to avoid scope collision with other Babel scripts)

/* ============================================================
   Icons — minimal set, lucide-style, currentColor
   ============================================================ */
const Icon = ({ d, size = 18, sw = 1.75, fill = "none", style }) => (
  <svg viewBox="0 0 24 24" width={size} height={size} fill={fill} stroke="currentColor"
    strokeWidth={sw} strokeLinecap="round" strokeLinejoin="round" style={style}>
    {Array.isArray(d) ? d.map((x, i) => <path key={i} d={x} />) : <path d={d} />}
  </svg>
);
const Icons = {
  cloud:    (p) => <Icon d="M17.5 19a4.5 4.5 0 0 0 0-9c-.5-3-3-5-6-5a6 6 0 0 0-6 6c0 .5.05 1 .15 1.5A4 4 0 0 0 6 19h11.5z" {...p} />,
  home:     (p) => <Icon d={["M3 11l9-8 9 8","M5 9.5V21h14V9.5"]} {...p} />,
  folder:   (p) => <Icon d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" {...p} />,
  image:    (p) => <Icon d={["M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5z","M3 16l5-5 5 5","M14 14l3-3 4 4","M9.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"]} {...p} />,
  share:    (p) => <Icon d={["M18 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6z","M6 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z","M18 22a3 3 0 1 0 0-6 3 3 0 0 0 0 6z","M8.6 13.5l6.8 4","M15.4 6.5l-6.8 4"]} {...p} />,
  star:     (p) => <Icon d="M12 3l2.7 5.5 6 .9-4.4 4.2 1 6-5.4-2.8L6.7 19.6l1-6L3.3 9.4l6-.9L12 3z" {...p} />,
  trash:    (p) => <Icon d={["M3 6h18","M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2","M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6","M10 11v6","M14 11v6"]} {...p} />,
  users:    (p) => <Icon d={["M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2","M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z","M22 21v-2a4 4 0 0 0-3-3.87","M16 3.13a4 4 0 0 1 0 7.75"]} {...p} />,
  upload:   (p) => <Icon d={["M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4","M17 8l-5-5-5 5","M12 3v12"]} {...p} />,
  download: (p) => <Icon d={["M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4","M7 10l5 5 5-5","M12 15V3"]} {...p} />,
  search:   (p) => <Icon d={["M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z","M21 21l-4.3-4.3"]} {...p} />,
  plus:     (p) => <Icon d={["M12 5v14","M5 12h14"]} {...p} />,
  more:     (p) => <Icon d={["M12 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2z","M19 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2z","M5 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"]} sw={2} {...p} />,
  sun:      (p) => <Icon d={["M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10z","M12 1v2","M12 21v2","M4.2 4.2l1.5 1.5","M18.3 18.3l1.5 1.5","M1 12h2","M21 12h2","M4.2 19.8l1.5-1.5","M18.3 5.7l1.5-1.5"]} {...p} />,
  moon:     (p) => <Icon d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" {...p} />,
  bell:     (p) => <Icon d={["M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9","M13.7 21a2 2 0 0 1-3.4 0"]} {...p} />,
  settings: (p) => <Icon d={["M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z","M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"]} {...p} />,
  link:     (p) => <Icon d={["M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1","M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"]} {...p} />,
  eye:      (p) => <Icon d={["M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z","M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"]} {...p} />,
  lock:     (p) => <Icon d={["M5 11h14a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1z","M8 11V7a4 4 0 0 1 8 0v4"]} {...p} />,
  check:    (p) => <Icon d="M5 13l4 4L19 7" {...p} />,
  x:        (p) => <Icon d={["M18 6L6 18","M6 6l12 12"]} {...p} />,
  chevR:    (p) => <Icon d="M9 6l6 6-6 6" {...p} />,
  chevL:    (p) => <Icon d="M15 6l-6 6 6 6" {...p} />,
  chevD:    (p) => <Icon d="M6 9l6 6 6-6" {...p} />,
  arrowR:   (p) => <Icon d={["M5 12h14","M13 6l6 6-6 6"]} {...p} />,
  film:     (p) => <Icon d={["M3 4a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4z","M7 3v18","M17 3v18","M3 8h4","M3 16h4","M17 8h4","M17 16h4","M3 12h18"]} {...p} />,
  music:    (p) => <Icon d={["M9 18V5l12-2v13","M9 18a3 3 0 1 1-6 0 3 3 0 0 1 6 0z","M21 16a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"]} {...p} />,
  doc:      (p) => <Icon d={["M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z","M14 2v6h6","M16 13H8","M16 17H8","M10 9H8"]} {...p} />,
  pdf:      (p) => <Icon d={["M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z","M14 2v6h6"]} {...p} />,
  zip:      (p) => <Icon d={["M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z","M14 2v6h6","M12 11v2","M12 15v2","M12 19v.01"]} {...p} />,
  cpu:      (p) => <Icon d={["M4 4h16v16H4z","M9 9h6v6H9z","M9 1v3","M15 1v3","M9 20v3","M15 20v3","M20 9h3","M20 14h3","M1 9h3","M1 14h3"]} {...p} />,
  hdd:      (p) => <Icon d={["M22 12H2","M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z","M6 16h.01","M10 16h.01"]} {...p} />,
  globe:    (p) => <Icon d={["M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z","M3 12h18","M12 3a14 14 0 0 1 0 18","M12 3a14 14 0 0 0 0 18"]} {...p} />,
  qr:       (p) => <Icon d={["M3 3h7v7H3z","M14 3h7v7h-7z","M3 14h7v7H3z","M14 14h3v3h-3z","M20 14v3","M14 20h3","M17 17v4","M21 17v4"]} {...p} />,
  copy:     (p) => <Icon d={["M9 9h11a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-9a2 2 0 0 1-2-2V11a2 2 0 0 1 0-2z","M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"]} {...p} />,
  filter:   (p) => <Icon d="M3 4h18l-7 9v6l-4 2v-8L3 4z" {...p} />,
  sort:     (p) => <Icon d={["M3 6h18","M6 12h12","M9 18h6"]} {...p} />,
  grid:     (p) => <Icon d={["M3 3h7v7H3z","M14 3h7v7h-7z","M3 14h7v7H3z","M14 14h7v7h-7z"]} {...p} />,
  list:     (p) => <Icon d={["M8 6h13","M8 12h13","M8 18h13","M3 6h.01","M3 12h.01","M3 18h.01"]} sw={2} {...p} />,
  info:     (p) => <Icon d={["M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z","M12 16v-4","M12 8h.01"]} {...p} />,
  zap:      (p) => <Icon d="M13 2L3 14h7l-1 8 10-12h-7l1-8z" {...p} />,
  shield:   (p) => <Icon d="M12 21s8-4 8-10V5l-8-3-8 3v6c0 6 8 10 8 10z" {...p} />,
  key:      (p) => <Icon d={["M21 2L13 10","M16 7l3 3","M11 12a4 4 0 1 1-5.66 5.66L2 21h3v-2h2v-2h2l2.34-2.34A4 4 0 0 1 11 12z"]} {...p} />,
};

/* ============================================================
   Theme + global state
   ============================================================ */
const ThemeCtx = React.createContext({ theme: "light", setTheme: () => {} });
const NavCtx   = React.createContext({ route: "dashboard", goto: () => {} });

const useNav = () => React.useContext(NavCtx);
const useTheme = () => React.useContext(ThemeCtx);

/* ============================================================
   Demo data
   ============================================================ */
const DEMO_FILES = [
  { id: "f1", type: "folder", name: "Photos famille", size: "4.2 GB", count: "1 248 fichiers", modified: "Aujourd'hui, 14:32", shared: true },
  { id: "f2", type: "folder", name: "Vacances Crète 2025", size: "2.1 GB", count: "342 fichiers", modified: "Hier, 09:14", shared: false },
  { id: "f3", type: "folder", name: "Documents", size: "486 MB", count: "84 fichiers", modified: "23 avril", shared: false },
  { id: "f4", type: "image", name: "IMG_4823.jpeg", size: "3.4 MB", modified: "Aujourd'hui, 12:08", shared: false, thumb: "https://images.unsplash.com/photo-1502082553048-f009c37129b9?w=200&q=70" },
  { id: "f5", type: "video", name: "Anniversaire Léa.mov", size: "284 MB", modified: "Hier, 18:42", shared: true },
  { id: "f6", type: "pdf", name: "Quittance loyer avril.pdf", size: "112 KB", modified: "1 avril", shared: false, public: true },
  { id: "f7", type: "image", name: "DSC_0021.jpg", size: "5.1 MB", modified: "26 avril", shared: false, thumb: "https://images.unsplash.com/photo-1469474968028-56623f02e42e?w=200&q=70" },
  { id: "f8", type: "audio", name: "Mix été 2025.flac", size: "62.4 MB", modified: "20 avril", shared: false },
  { id: "f9", type: "doc", name: "Recettes-cuisine.md", size: "18 KB", modified: "18 avril", shared: false },
  { id: "f10", type: "archive", name: "Backup-config.tar.gz", size: "1.2 GB", modified: "12 avril", shared: false },
];

const DEMO_GALLERY = [
  "https://images.unsplash.com/photo-1502082553048-f009c37129b9?w=600&q=70",
  "https://images.unsplash.com/photo-1469474968028-56623f02e42e?w=600&q=70",
  "https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?w=600&q=70",
  "https://images.unsplash.com/photo-1426604966848-d7adac402bff?w=600&q=70",
  "https://images.unsplash.com/photo-1418065460487-3956ef138ef0?w=600&q=70",
  "https://images.unsplash.com/photo-1441974231531-c6227db76b6e?w=600&q=70",
  "https://images.unsplash.com/photo-1447752875215-b2761acb3c5d?w=600&q=70",
  "https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?w=600&q=70",
  "https://images.unsplash.com/photo-1501785888041-af3ef285b470?w=600&q=70",
  "https://images.unsplash.com/photo-1472214103451-9374bd1c798e?w=600&q=70",
  "https://images.unsplash.com/photo-1505765050516-f72dcac9c60a?w=600&q=70",
  "https://images.unsplash.com/photo-1444858345-fd0aa83d4f3e?w=600&q=70",
  "https://images.unsplash.com/photo-1493246507139-91e8fad9978e?w=600&q=70",
  "https://images.unsplash.com/photo-1444723121867-7a241cacace9?w=600&q=70",
  "https://images.unsplash.com/photo-1533105079780-92b9be482077?w=600&q=70",
  "https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=600&q=70",
];

const DEMO_USERS = [
  { id: "u1", name: "Ronan", email: "ronan@home.lan", role: "Admin",   color: "linear-gradient(135deg, #2b5fff, #6e8bff)", initials: "R" },
  { id: "u2", name: "Émilie", email: "emilie@home.lan", role: "Membre", color: "linear-gradient(135deg, #ec4899, #f472b6)", initials: "É" },
  { id: "u3", name: "Léa",    email: "lea@home.lan",    role: "Enfant", color: "linear-gradient(135deg, #10b981, #34d399)", initials: "L" },
  { id: "u4", name: "Tom",    email: "tom@home.lan",    role: "Enfant", color: "linear-gradient(135deg, #f59e0b, #fbbf24)", initials: "T" },
];

const fileTypeIcon = (type) => ({
  folder: <Icons.folder />, image: <Icons.image />, video: <Icons.film />,
  pdf: <Icons.pdf />, doc: <Icons.doc />, audio: <Icons.music />, archive: <Icons.zip />,
}[type] || <Icons.doc />);

const FileThumb = ({ file, size = 36 }) => {
  const cls = `file-thumb tt-${file.type}`;
  const style = { width: size, height: size };
  if (file.thumb) return <div className={cls} style={style}><img src={file.thumb} alt="" /></div>;
  return <div className={cls} style={style}>{fileTypeIcon(file.type)}</div>;
};

/* ============================================================
   Sidebar
   ============================================================ */
const Sidebar = () => {
  const { route, goto } = useNav();
  const items = [
    { id: "dashboard", icon: <Icons.home className="ico" size={17} />, label: "Tableau de bord" },
    { id: "files",     icon: <Icons.folder className="ico" size={17} />, label: "Mes fichiers" },
    { id: "gallery",   icon: <Icons.image className="ico" size={17} />, label: "Galerie", badge: "1.2k" },
    { id: "shares",    icon: <Icons.share className="ico" size={17} />, label: "Partages", badge: "8" },
    { id: "starred",   icon: <Icons.star className="ico" size={17} />, label: "Favoris" },
    { id: "trash",     icon: <Icons.trash className="ico" size={17} />, label: "Corbeille" },
  ];
  const family = [
    { id: "u1", name: "Ronan",   color: DEMO_USERS[0].color, online: true },
    { id: "u2", name: "Émilie",  color: DEMO_USERS[1].color, online: true },
    { id: "u3", name: "Léa",     color: DEMO_USERS[2].color, online: false },
    { id: "u4", name: "Tom",     color: DEMO_USERS[3].color, online: false },
  ];
  return (
    <aside className="sidebar">
      <div className="sb-brand">
        <div className="sb-brand-icon"><Icons.cloud size={17} sw={2} /></div>
        <div>
          <div className="sb-brand-name">HomeCloud</div>
          <div className="sb-brand-sub">home.lan · v1.4</div>
        </div>
      </div>

      <button className="btn btn-primary" style={{justifyContent:'flex-start', width:'100%', padding:'10px 14px', borderRadius:10, marginBottom:6}}>
        <Icons.plus size={16} sw={2.2} /> Nouveau
      </button>

      <div className="sb-section-title">Espace</div>
      {items.map(it => (
        <div key={it.id} className={`sb-item ${route === it.id ? 'active' : ''}`} onClick={() => goto(it.id)}>
          {it.icon}
          <span>{it.label}</span>
          {it.badge && <span className="badge">{it.badge}</span>}
        </div>
      ))}

      <div className="sb-section-title">Famille</div>
      {family.map(u => (
        <div key={u.id} className="sb-item" style={{padding:'6px 10px'}}>
          <div style={{
            width: 22, height: 22, borderRadius: '50%',
            background: u.color, color:'white', display:'grid', placeItems:'center',
            fontSize: 10.5, fontWeight: 600, position:'relative',
          }}>
            {u.name[0]}
            {u.online && <span style={{
              position:'absolute', bottom:-1, right:-1, width:8, height:8,
              borderRadius:'50%', background:'#10b981', border:'2px solid var(--hc-bg)'
            }} />}
          </div>
          <span style={{fontSize:13}}>{u.name}</span>
        </div>
      ))}

      <div className="sb-storage">
        <div className="row between" style={{fontSize:12, color:'var(--hc-text-2)', fontWeight:500}}>
          <span>Stockage</span>
          <span className="mono">68%</span>
        </div>
        <div className="sb-storage-bar"><div className="sb-storage-fill" style={{width:'68%'}}/></div>
        <div style={{fontSize:11.5, color:'var(--hc-text-3)'}}>
          <span className="mono" style={{color:'var(--hc-text-2)'}}>1.36 To</span> sur <span className="mono">2 To</span>
        </div>
      </div>
    </aside>
  );
};

/* ============================================================
   Topbar
   ============================================================ */
const Topbar = ({ crumbs = [], onSearch, rightExtras }) => {
  const { theme, setTheme } = useTheme();
  return (
    <div className="topbar">
      <div className="crumbs">
        {crumbs.map((c, i) => (
          <React.Fragment key={i}>
            {i > 0 && <Icons.chevR size={14} className="sep" style={{opacity:0.5}} />}
            <span className={`crumb ${i === crumbs.length-1 ? 'curr' : ''}`}>{c}</span>
          </React.Fragment>
        ))}
      </div>
      <div className="flex-1" />
      <div className="searchbar" onClick={onSearch}>
        <Icons.search size={15} />
        <span style={{fontSize:13.5}}>Rechercher fichiers, dossiers…</span>
        <span className="kbd">⌘K</span>
      </div>
      {rightExtras}
      <button className="btn btn-ghost btn-icon" title="Notifications">
        <Icons.bell size={16} />
      </button>
      <button className="btn btn-ghost btn-icon" onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')} title="Thème">
        {theme === 'dark' ? <Icons.sun size={16} /> : <Icons.moon size={16} />}
      </button>
      <div style={{
        width: 30, height: 30, borderRadius: '50%',
        background: 'linear-gradient(135deg, #2b5fff, #6e8bff)',
        color: 'white', display:'grid', placeItems:'center',
        fontSize: 12, fontWeight: 600, marginLeft: 4,
        boxShadow: '0 0 0 2px var(--hc-bg)',
      }}>R</div>
    </div>
  );
};

/* Export to window so other JSX scripts can use these */
Object.assign(window, {
  Icon, Icons, ThemeCtx, NavCtx, useNav, useTheme,
  DEMO_FILES, DEMO_GALLERY, DEMO_USERS, FileThumb, fileTypeIcon,
  Sidebar, Topbar,
});
