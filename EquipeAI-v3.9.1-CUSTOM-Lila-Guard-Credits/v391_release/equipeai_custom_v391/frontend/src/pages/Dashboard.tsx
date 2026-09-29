import {useEffect,useMemo,useState} from 'react';
import {Link} from 'react-router-dom';
import {api} from '../api';
import {useAuth} from '../AuthContext';
import {aggregateContextQuality} from '../utils/contextQuality';
import {
  FiArrowRight,FiArrowUpRight,FiBriefcase,FiCheckCircle,FiChevronRight,FiCreditCard,FiDollarSign,
  FiEdit3,FiFileText,FiMessageCircle,FiStar,FiTarget,FiZap
} from 'react-icons/fi';

type Agent={id:number;slug:string;name:string;category:string};
const actionMeta:Record<string,{icon:any;label:string;description:string;tone:string}>={
  atendimento:{icon:FiMessageCircle,label:'Responder cliente',description:'Transforme uma mensagem em uma resposta pronta, alinhada à sua empresa.',tone:'blue'},
  marketing:{icon:FiEdit3,label:'Criar conteúdo',description:'Crie conteúdo com objetivo, público e contexto comercial definidos.',tone:'violet'},
  comercial:{icon:FiDollarSign,label:'Criar proposta',description:'Estruture proposta, orçamento ou mensagem comercial com clareza.',tone:'emerald'},
  avaliacoes:{icon:FiStar,label:'Responder avaliação',description:'Responda avaliações mantendo tom, reputação e contexto.',tone:'amber'},
  documentos:{icon:FiFileText,label:'Analisar documento',description:'Resuma, extraia e encontre pontos relevantes em seus arquivos.',tone:'slate'},
  'follow-up':{icon:FiTarget,label:'Fazer follow-up',description:'Retome conversas sem parecer insistente e avance o próximo passo.',tone:'rose'}
};

export default function Dashboard(){
  const{user}=useAuth();
  const[data,setData]=useState<any>(null);
  const[agents,setAgents]=useState<Agent[]>([]);
  const[company,setCompany]=useState<any>({});

  useEffect(()=>{
    Promise.all([api.get('/dashboard'),api.get('/agents'),api.get('/company')]).then(([d,a,c])=>{
      setData(d.data);setAgents(a.data.agents||[]);setCompany(c.data.company||{});
    });
  },[]);

  const actions=useMemo(()=>agents.filter(a=>actionMeta[a.slug]).slice(0,6),[agents]);
  const primary=actions.slice(0,2),secondary=actions.slice(2);
  const contextQuality=useMemo(()=>aggregateContextQuality([
    {field:'segment',value:company.segment,targetLength:45,weight:1.1},
    {field:'description',value:company.description,targetLength:180,weight:1.4},
    {field:'target_audience',value:company.target_audience,targetLength:170,weight:1.25},
    {field:'differentials',value:company.differentials,targetLength:160,weight:1.2},
    {field:'business_hours',value:company.business_hours,targetLength:90,weight:.6,optional:true},
    {field:'specific_rules',value:company.specific_rules,targetLength:170,weight:.8,optional:true},
  ]),[company]);
  const recent=data?.recent||[];

  return <div className="page dashboard-page dashboard-v4">
    <section className="workspace-intro">
      <div className="workspace-intro-copy">
        <span className="workspace-breadcrumb">Workspace <FiChevronRight/> Visão geral</span>
        <h1>Bom trabalho, {user?.name.split(' ')[0]}.</h1>
        <p>Escolha o próximo trabalho. O contexto da sua empresa acompanha cada ferramenta automaticamente.</p>
        <div className="workspace-summary-line">
          <span><i className="status-dot ok"/> Operação disponível</span>
          <span>{recent.length} atividades recentes</span>
          <span>{data?.month_usage?.credits??0} créditos usados no mês</span>
        </div>
      </div>
      <Link to="/app/creditos" className="balance-hero-card">
        <div className="balance-hero-icon"><FiZap/></div>
        <div><small>Saldo disponível</small><strong>{data?.credits??'—'}</strong><span>créditos</span></div>
        <FiArrowUpRight/>
      </Link>
    </section>

    <section className="workbench-section">
      <div className="section-line-head"><div><h2>Começar um trabalho</h2><p>Acesso direto aos fluxos mais usados.</p></div><Link to="/app/agentes">Ver todas as ferramentas <FiArrowRight/></Link></div>
      <div className="workbench-grid">
        {primary.map((agent,index)=>{const meta=actionMeta[agent.slug];const Icon=meta.icon;return <Link className={`work-card work-card-featured tone-${meta.tone}`} to={`/app/agentes/${agent.id}`} key={agent.id}>
          <div className="work-card-top"><span className="work-card-icon"><Icon/></span><span className="work-card-number">0{index+1}</span></div>
          <div><small>{agent.category}</small><h3>{meta.label}</h3><p>{meta.description}</p></div>
          <span className="work-card-cta">Abrir ferramenta <FiArrowRight/></span>
        </Link>})}
        <div className="work-card-secondary-grid">
          {secondary.map((agent,index)=>{const meta=actionMeta[agent.slug];const Icon=meta.icon;return <Link className={`work-card work-card-compact tone-${meta.tone}`} to={`/app/agentes/${agent.id}`} key={agent.id}>
            <span className="work-card-icon"><Icon/></span><div><small>{agent.category}</small><h3>{meta.label}</h3></div><FiArrowUpRight className="work-card-open"/><span className="work-card-number">0{index+3}</span>
          </Link>})}
        </div>
      </div>
    </section>

    <div className="dashboard-v4-grid">
      <section className="surface-card activity-v4">
        <div className="surface-card-head"><div><h2>Atividade recente</h2><p>O que foi executado neste workspace.</p></div><Link to="/app/historico">Histórico completo <FiArrowUpRight/></Link></div>
        <div className="activity-table-v4">
          {recent.length?recent.map((x:any)=><div className="activity-row-v4" key={x.id}>
            <span className="activity-status"><i/></span>
            <div className="activity-name"><strong>{x.agent_name}</strong><small>Execução concluída</small></div>
            <time>{new Date(x.created_at).toLocaleString('pt-BR',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'})}</time>
            <span className="activity-cost">-{x.credits_used} cr.</span>
            <FiChevronRight/>
          </div>):<div className="empty-state-v4"><FiCheckCircle/><div><strong>Workspace pronto</strong><p>Sua primeira execução aparecerá aqui.</p></div></div>}
        </div>
      </section>

      <aside className="workspace-pulse">
        <section className="surface-card pulse-card">
          <div className="pulse-head"><div><span>Plano atual</span><strong>{data?.subscription?.name||'Grátis'}</strong></div><FiCreditCard/></div>
          <div className="pulse-metric"><strong>{data?.credits??0}</strong><span>créditos disponíveis</span></div>
          <div className="pulse-rule"/>
          <div className="pulse-row"><span>Uso neste mês</span><strong>{data?.month_usage?.credits??0} cr.</strong></div>
          <Link className="pulse-link" to="/app/assinatura">Gerenciar assinatura <FiArrowRight/></Link>
        </section>
        <section className="surface-card context-pulse-card">
          <div className="pulse-head"><div><span>Contexto empresarial</span><strong>{contextQuality.label}</strong></div><FiBriefcase/></div>
          <div className="context-score-v4"><strong>{contextQuality.score}</strong><span>/100</span></div>
          <div className="context-track-v4"><i style={{width:`${contextQuality.score}%`}}/></div>
          <p>{contextQuality.acceptable?'O contexto já está pronto para orientar seus agentes.':'Há informações que ainda deixam as respostas mais genéricas do que deveriam.'}</p>
          <Link className="pulse-link" to="/app/empresa">Revisar contexto <FiArrowRight/></Link>
        </section>
      </aside>
    </div>
  </div>
}
