import {useEffect,useMemo,useState} from 'react';
import {api} from '../api';
import {Link} from 'react-router-dom';
import {FiArrowRight,FiArrowUpRight,FiBriefcase,FiClipboard,FiFileText,FiMessageCircle,FiSearch,FiStar,FiTarget,FiTrendingUp} from 'react-icons/fi';
const icons:any={'message-circle':FiMessageCircle,chat:FiMessageCircle,megaphone:FiTrendingUp,smartphone:FiTrendingUp,briefcase:FiBriefcase,star:FiStar,file:FiFileText,'file-text':FiFileText,clipboard:FiClipboard,target:FiTarget};
export default function Agents(){
  const[a,setA]=useState<any[]>([]);const[q,setQ]=useState('');
  useEffect(()=>{api.get('/agents').then(r=>setA(r.data.agents))},[]);
  const visible=useMemo(()=>{const query=q.trim().toLocaleLowerCase('pt-BR');return query?a.filter(x=>`${x.name} ${x.category} ${x.description}`.toLocaleLowerCase('pt-BR').includes(query)):a},[a,q]);
  return <div className="page agents-v4">
    <div className="page-head"><div><span className="workspace-breadcrumb">Workspace <FiArrowRight/> Ferramentas</span><h1>Ferramentas de trabalho</h1><p>Cada fluxo já recebe o contexto da empresa e usa o modelo configurado pelo sistema. Você escolhe a tarefa, não a tecnologia.</p></div><div className="info-chip"><span className="system-dot"/> {a.length} ferramentas disponíveis</div></div>
    <div className="tool-library-bar"><div><strong>Biblioteca</strong><span>Encontre o fluxo certo para o trabalho que precisa fazer.</span></div><label className="tool-search"><FiSearch/><input value={q} onChange={e=>setQ(e.target.value)} placeholder="Buscar ferramenta…"/></label></div>
    <div className="agent-grid agent-grid-v4">{visible.map((x,i)=>{const Icon=icons[x.icon]||FiMessageCircle;return <Link to={`/app/agentes/${x.id}`} className="agent-card" key={x.id}><div className="agent-card-top"><span className="agent-index">{String(i+1).padStart(2,'0')}</span><div className="iconbox"><Icon/></div><span className="cost-chip">{x.credit_cost} cr.</span></div><small>{x.category}</small><h3>{x.name}</h3><p>{x.description}</p><span className="agent-open">Abrir ferramenta <FiArrowUpRight/></span></Link>})}</div>
    {!visible.length&&<div className="empty-state-v4"><FiSearch/><div><strong>Nenhuma ferramenta encontrada</strong><p>Tente outro termo de busca.</p></div></div>}
  </div>
}
