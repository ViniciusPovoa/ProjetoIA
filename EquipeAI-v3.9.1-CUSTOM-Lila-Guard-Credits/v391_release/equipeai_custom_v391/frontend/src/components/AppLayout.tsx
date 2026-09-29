import {useEffect,useMemo,useRef,useState} from 'react';
import {NavLink,Outlet,useLocation,useNavigate} from 'react-router-dom';
import {useAuth} from '../AuthContext';
import {api} from '../api';
import Brand from './Brand';
import LilaAssistant from './LilaAssistant';
import {
  FiActivity,FiArrowRight,FiBriefcase,FiChevronDown,FiChevronRight,FiChevronsLeft,FiChevronsRight,
  FiClock,FiCommand,FiCreditCard,FiFileText,FiFolder,FiGrid,FiHelpCircle,FiHome,FiLogOut,
  FiMessageCircle,FiSearch,FiSettings,FiShield,FiSmartphone,FiStar,FiTarget,FiZap,FiClipboard,FiX
} from 'react-icons/fi';

const management=[
  ['/app/historico',FiClock,'Histórico'],['/app/empresa',FiBriefcase,'Minha empresa'],['/app/arquivos',FiFolder,'Arquivos'],
  ['/app/creditos',FiZap,'Uso e créditos'],['/app/assinatura',FiCreditCard,'Assinatura'],['/app/configuracoes',FiSettings,'Configurações']
] as const;
const agentIcons:Record<string,typeof FiGrid>={atendimento:FiMessageCircle,marketing:FiSmartphone,comercial:FiBriefcase,avaliacoes:FiStar,documentos:FiFileText,administrativo:FiClipboard,'follow-up':FiTarget};
type Agent={id:number;name:string;slug:string};

type CommandItem={label:string;description:string;to:string;icon:any;group:string};

export default function AppLayout(){
  const{user,logout}=useAuth();
  const nav=useNavigate();
  const location=useLocation();
  const[agents,setAgents]=useState<Agent[]>([]);
  const[collapsed,setCollapsed]=useState(()=>localStorage.getItem('workspace_sidebar_collapsed')==='1');
  const[toolsOpen,setToolsOpen]=useState(false);
  const[managementOpen,setManagementOpen]=useState(false);
  const[commandOpen,setCommandOpen]=useState(false);
  const[query,setQuery]=useState('');
  const[activeIndex,setActiveIndex]=useState(0);
  const searchRef=useRef<HTMLInputElement>(null);

  useEffect(()=>{api.get('/agents').then(r=>setAgents(r.data?.agents||[])).catch(()=>setAgents([]))},[]);
  useEffect(()=>{
    if(location.pathname.startsWith('/app/agentes/'))setToolsOpen(true);
    if(management.some(([to])=>location.pathname.startsWith(to)))setManagementOpen(true);
  },[location.pathname]);
  useEffect(()=>{localStorage.setItem('workspace_sidebar_collapsed',collapsed?'1':'0')},[collapsed]);
  useEffect(()=>{
    const handle=(event:KeyboardEvent)=>{
      if((event.metaKey||event.ctrlKey)&&event.key.toLowerCase()==='k'){event.preventDefault();setCommandOpen(v=>!v)}
      if(event.key==='Escape')setCommandOpen(false);
    };
    window.addEventListener('keydown',handle);return()=>window.removeEventListener('keydown',handle);
  },[]);
  useEffect(()=>{if(commandOpen){setActiveIndex(0);setTimeout(()=>searchRef.current?.focus(),20)}else setQuery('')},[commandOpen]);

  const agentPath=useMemo(()=>location.pathname.startsWith('/app/agentes'),[location.pathname]);
  const managementPath=useMemo(()=>management.some(([to])=>location.pathname.startsWith(to)),[location.pathname]);
  const signOut=async()=>{await logout();nav('/')};
  const toggleTools=()=>{
    if(window.matchMedia('(max-width: 1050px)').matches){nav('/app/agentes');return;}
    if(collapsed){setCollapsed(false);setToolsOpen(true);return;}
    setToolsOpen(v=>!v);
  };

  const commands=useMemo<CommandItem[]>(()=>[
    {label:'Visão geral',description:'Abrir o painel principal',to:'/app',icon:FiHome,group:'Navegação'},
    ...agents.map(agent=>({label:agent.name,description:'Abrir ferramenta',to:`/app/agentes/${agent.id}`,icon:agentIcons[agent.slug]||FiGrid,group:'Ferramentas'})),
    ...management.map(([to,icon,label])=>({label,description:'Abrir área de gestão',to,icon,group:'Gestão'})),
    ...(user?.role==='admin'?[{label:'Administração',description:'Configurações do sistema',to:'/app/admin',icon:FiShield,group:'Sistema'}]:[])
  ],[agents,user?.role]);
  const visibleCommands=useMemo(()=>{const q=query.trim().toLocaleLowerCase('pt-BR');return q?commands.filter(item=>`${item.label} ${item.description} ${item.group}`.toLocaleLowerCase('pt-BR').includes(q)):commands},[commands,query]);
  useEffect(()=>setActiveIndex(0),[query]);
  const go=(to:string)=>{setCommandOpen(false);nav(to)};

  return <div className={`app-shell workspace-shell ${collapsed?'sidebar-is-collapsed':''}`}>
    <aside className="sidebar workspace-rail">
      <div className="rail-brand-row">
        <div className="brand-sidebar"><Brand to="/app"/></div>
        <button className="rail-collapse-btn" type="button" onClick={()=>setCollapsed(v=>!v)} title={collapsed?'Expandir menu':'Recolher menu'} aria-label={collapsed?'Expandir menu lateral':'Recolher menu lateral'}>
          {collapsed?<FiChevronsRight/>:<FiChevronsLeft/>}
        </button>
      </div>

      <nav className="side-nav workspace-nav" aria-label="Navegação principal">
        <span className="nav-section-label">Workspace</span>
        <NavLink to="/app" end title="Visão geral"><FiHome/><span>Visão geral</span><FiChevronRight className="nav-chevron"/></NavLink>

        <div className={`nav-collapsible tools-group ${toolsOpen?'is-open':''} ${agentPath?'has-active':''}`}>
          <button className="nav-group-trigger" type="button" onClick={toggleTools} title="Ferramentas" aria-expanded={toolsOpen}>
            <FiGrid/><span>Ferramentas</span>{toolsOpen?<FiChevronDown className="nav-chevron"/>:<FiChevronRight className="nav-chevron"/>}
          </button>
          <div className="nav-group-panel agent-subnav">
            <NavLink to="/app/agentes" end><FiGrid/><span>Todas as ferramentas</span></NavLink>
            {agents.map(agent=>{const Icon=agentIcons[agent.slug]||FiGrid;return <NavLink key={agent.id} to={`/app/agentes/${agent.id}`} title={agent.name}><Icon/><span>{agent.name}</span></NavLink>})}
          </div>
        </div>

        <div className={`nav-collapsible management-group ${managementOpen?'is-open':''} ${managementPath?'has-active':''}`}>
          <button className="nav-group-trigger" type="button" onClick={()=>{if(collapsed)setCollapsed(false);setManagementOpen(v=>!v)}} title="Gestão" aria-expanded={managementOpen}>
            <FiBriefcase/><span>Gestão</span>{managementOpen?<FiChevronDown className="nav-chevron"/>:<FiChevronRight className="nav-chevron"/>}
          </button>
          <div className="nav-group-panel">
            {management.map(([to,Icon,label])=><NavLink key={to} to={to} title={label}><Icon/><span>{label}</span><FiChevronRight className="nav-chevron"/></NavLink>)}
          </div>
        </div>

        {user?.role==='admin'&&<div className="nav-admin-block"><span className="nav-section-label">Sistema</span><NavLink to="/app/admin" title="Administração"><FiShield/><span>Administração</span><FiChevronRight className="nav-chevron"/></NavLink></div>}
      </nav>

      <div className="sidebar-foot rail-account">
        <div className="avatar">{user?.name?.slice(0,1).toUpperCase()}</div>
        <div className="sidebar-user"><span>{user?.name}</span><small>{user?.email}</small></div>
        <button className="icon-btn" onClick={signOut} title="Sair" aria-label="Sair"><FiLogOut/></button>
      </div>
    </aside>

    <section className="workspace-stage">
      <header className="app-topbar topbar-v4">
        <div className="topbar-left-v4">
          <div className="topbar-health"><FiActivity/><span>Workspace operacional</span><i/></div>
          <button className="command-trigger" type="button" onClick={()=>setCommandOpen(true)}><FiSearch/><span>Buscar ferramenta ou área</span><kbd><FiCommand/> K</kbd></button>
        </div>
        <div className="topbar-actions"><a href="mailto:suporte@ia.vdeveloper.com.br" className="topbar-help"><FiHelpCircle/> Suporte</a><div className="topbar-account"><span className="mini-avatar">{user?.name?.slice(0,1).toUpperCase()}</span><div><span>{user?.name}</span><small>{user?.role==='admin'?'Administrador':'Membro'}</small></div></div></div>
      </header>
      <main className="content"><Outlet/></main>
    </section>

    <LilaAssistant/>

    {commandOpen&&<div className="command-overlay" role="dialog" aria-modal="true" aria-label="Busca rápida" onMouseDown={e=>{if(e.target===e.currentTarget)setCommandOpen(false)}}>
      <div className="command-palette">
        <div className="command-search"><FiSearch/><input ref={searchRef} value={query} onChange={e=>setQuery(e.target.value)} onKeyDown={e=>{if(e.key==='ArrowDown'){e.preventDefault();setActiveIndex(i=>Math.min(i+1,Math.max(0,Math.min(11,visibleCommands.length-1))))}if(e.key==='ArrowUp'){e.preventDefault();setActiveIndex(i=>Math.max(0,i-1))}if(e.key==='Enter'&&visibleCommands[activeIndex]){e.preventDefault();go(visibleCommands[activeIndex].to)}}} placeholder="Buscar ferramenta, histórico, empresa…"/><button onClick={()=>setCommandOpen(false)} aria-label="Fechar"><FiX/></button></div>
        <div className="command-results">
          {visibleCommands.length?visibleCommands.slice(0,12).map((item,index)=>{const Icon=item.icon;return <button className={index===activeIndex?'is-active':''} key={`${item.to}-${index}`} onMouseEnter={()=>setActiveIndex(index)} onClick={()=>go(item.to)}><span className="command-item-icon"><Icon/></span><span><strong>{item.label}</strong><small>{item.description}</small></span><em>{item.group}</em><FiArrowRight/></button>}):<div className="command-empty">Nenhum resultado para “{query}”.</div>}
        </div>
        <div className="command-footer"><span><kbd>↑</kbd><kbd>↓</kbd> navegar</span><span><kbd>Enter</kbd> abrir</span><span><kbd>Esc</kbd> fechar</span></div>
      </div>
    </div>}
  </div>
}
