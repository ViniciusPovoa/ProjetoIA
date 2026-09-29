import {FormEvent,useEffect,useMemo,useState} from 'react';
import {api} from '../api';
import {
  FiAlertCircle,FiArrowRight,FiBarChart2,FiBriefcase,FiCheck,FiCheckCircle,FiEdit3,FiHelpCircle,
  FiPlus,FiRotateCcw,FiSave,FiShield,FiTrash2,FiX,FiZap
} from 'react-icons/fi';
import {aggregateContextQuality,evaluateContext,ContextQuality,CONTEXT_ACCEPTABLE_SCORE} from '../utils/contextQuality';

type FieldType='text'|'textarea'|'tone'|'email'|'url'|'phone';
type CompanyField={
  key:string;label:string;type:FieldType;tooltip:string;placeholder?:string;maxLength?:number;
  contextual?:boolean;targetLength?:number;improvable?:boolean;span?:boolean;optionalContext?:boolean;
};
type FieldGroup={title:string;eyebrow:string;description:string;fields:CompanyField[]};
type Improvement={field:string;before:string;after:string;beforeScore:number;afterScore:number};
type Assistance={score:number;missing:string[]};

const groups:FieldGroup[]=[
  {title:'Identidade',eyebrow:'Essência do negócio',description:'Defina o que a empresa é, o que faz e como deve ser compreendida pelos agentes.',fields:[
    {key:'name',label:'Nome da empresa',type:'text',tooltip:'Nome comercial usado nas respostas e materiais gerados.',placeholder:'Ex.: Clínica Aurora',maxLength:150},
    {key:'segment',label:'Segmento',type:'text',tooltip:'Área principal de atuação. Prefira um nicho específico a uma categoria ampla.',placeholder:'Ex.: Odontologia estética e preventiva',maxLength:150,contextual:true,targetLength:45,improvable:true},
    {key:'description',label:'Descrição da empresa',type:'textarea',tooltip:'Explique o que a empresa faz, para quem atende e como trabalha. Essa é uma das fontes mais importantes para os agentes.',placeholder:'Ex.: Clínica odontológica focada em estética e prevenção, atendendo adultos com acompanhamento individual...',maxLength:1600,contextual:true,targetLength:180,improvable:true,span:true}
  ]},
  {title:'Contato e operação',eyebrow:'Informações operacionais',description:'Dados que podem aparecer em respostas quando um cliente pergunta como, onde ou quando falar com a empresa.',fields:[
    {key:'phone',label:'Telefone',type:'phone',tooltip:'Telefone principal da empresa com DDD.',placeholder:'(34) 3333-3333',maxLength:30},
    {key:'whatsapp',label:'WhatsApp',type:'phone',tooltip:'Número de WhatsApp usado no atendimento comercial.',placeholder:'(34) 99999-9999',maxLength:30},
    {key:'email',label:'E-mail comercial',type:'email',tooltip:'E-mail que pode ser informado aos clientes nas respostas.',placeholder:'contato@empresa.com.br',maxLength:190},
    {key:'address',label:'Endereço',type:'text',tooltip:'Endereço ou região de atendimento. Cadastre somente informações que podem ser usadas pelos agentes.',placeholder:'Av. Exemplo, 100 — Centro, Uberlândia/MG',maxLength:500},
    {key:'website',label:'Site',type:'url',tooltip:'Site oficial da empresa.',placeholder:'https://suaempresa.com.br',maxLength:255},
    {key:'instagram',label:'Instagram',type:'text',tooltip:'Usuário ou URL do Instagram oficial.',placeholder:'@suaempresa',maxLength:255},
    {key:'business_hours',label:'Horário de funcionamento',type:'textarea',tooltip:'Informe dias e horários e, quando necessário, exceções como sábado por agendamento.',placeholder:'Segunda a sexta, das 8h às 18h. Sábados somente com agendamento.',maxLength:900,contextual:true,targetLength:90,improvable:true,span:true,optionalContext:true}
  ]},
  {title:'Mercado e proposta de valor',eyebrow:'Contexto comercial',description:'Ajude marketing, atendimento e comercial a entender o que você oferece, para quem e por que sua empresa é escolhida.',fields:[
    {key:'products',label:'Produtos e soluções',type:'textarea',tooltip:'Liste produtos, soluções ou linhas que complementam o catálogo de serviços.',placeholder:'Ex.: alinhadores, clareamento supervisionado e kits de higiene...',maxLength:1800,contextual:true,targetLength:150,improvable:true,span:true,optionalContext:true},
    {key:'target_audience',label:'Público-alvo',type:'textarea',tooltip:'Descreva perfil, necessidade principal e algum recorte útil, como localização, setor ou momento de compra.',placeholder:'Ex.: adultos de 25 a 50 anos de Uberlândia que buscam melhorar o sorriso com atendimento particular...',maxLength:1800,contextual:true,targetLength:170,improvable:true,span:true},
    {key:'differentials',label:'Diferenciais',type:'textarea',tooltip:'Use fatos verificáveis: processo, atendimento, tecnologia, suporte ou características reais. Evite “somos os melhores”.',placeholder:'Ex.: atendimento com hora marcada, acompanhamento pós-procedimento e equipamentos digitais...',maxLength:1800,contextual:true,targetLength:160,improvable:true,span:true}
  ]},
  {title:'Voz e limites',eyebrow:'Padrão de comunicação',description:'Defina como a plataforma deve escrever e quais regras nunca podem ser ignoradas.',fields:[
    {key:'communication_tone',label:'Tom de comunicação',type:'tone',tooltip:'Tom predominante das respostas. Regras específicas abaixo têm prioridade.'},
    {key:'avoid_words',label:'Palavras e abordagens a evitar',type:'textarea',tooltip:'Termos, promessas, gírias ou abordagens que não combinam com sua marca.',placeholder:'Ex.: não usar “promoção imperdível”; não prometer resultado garantido.',maxLength:1300,contextual:true,targetLength:100,improvable:true,span:true,optionalContext:true},
    {key:'preferred_words',label:'Palavras e abordagens preferidas',type:'textarea',tooltip:'Expressões e formas de tratamento que representam bem sua marca.',placeholder:'Ex.: usar “paciente”, “avaliação individual” e linguagem acolhedora.',maxLength:1300,contextual:true,targetLength:100,improvable:true,span:true,optionalContext:true},
    {key:'specific_rules',label:'Regras específicas',type:'textarea',tooltip:'Regras obrigatórias, como “nunca dar diagnóstico por mensagem” ou “sempre confirmar prazo antes de prometer”.',placeholder:'Ex.: nunca dar diagnóstico por WhatsApp; sempre orientar avaliação presencial em casos clínicos.',maxLength:2000,contextual:true,targetLength:170,improvable:true,span:true,optionalContext:true},
    {key:'additional_info',label:'Informações adicionais',type:'textarea',tooltip:'Contexto relevante que não se encaixa nos campos anteriores.',placeholder:'Ex.: estacionamento conveniado, formas de atendimento ou observações sazonais.',maxLength:1800,contextual:true,targetLength:130,improvable:true,span:true,optionalContext:true}
  ]}
];

const contextualFields=groups.flatMap(group=>group.fields).filter(field=>field.contextual);

function qualityClass(quality:ContextQuality){return `context-level context-level-${quality.level}`}
function validateField(field:CompanyField,value:unknown):string{
  const text=String(value??'').trim();
  if(!text)return '';
  if(field.maxLength&&text.length>field.maxLength)return `Use no máximo ${field.maxLength} caracteres.`;
  if(field.type==='email'&&!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i.test(text))return 'Informe um e-mail válido.';
  if(field.type==='phone'&&text.replace(/\D/g,'').length<8)return 'Informe um telefone válido com DDD quando aplicável.';
  if(field.type==='url'&&!/^(https?:\/\/)?([\w-]+\.)+[\w-]{2,}(\/.*)?$/i.test(text))return 'Informe um site válido, como suaempresa.com.br.';
  if(field.key==='instagram'&&!/^(@?[a-z0-9._]{2,30}|https?:\/\/(www\.)?instagram\.com\/.+)$/i.test(text))return 'Use @usuario ou a URL completa do Instagram.';
  return '';
}
function Tooltip({text}:{text:string}){return <span className="field-tooltip" tabIndex={0} data-tooltip={text} aria-label={text}><FiHelpCircle/></span>}
function QualityMeter({quality}:{quality:ContextQuality}){return <div className="context-quality compact"><div className="context-quality-track" aria-hidden="true"><i style={{width:`${quality.score}%`}}/></div><div className="context-quality-meta"><span className={qualityClass(quality)}>{quality.label}</span><small>{quality.score}/100</small></div></div>}

export default function Company(){
  const[c,setC]=useState<any>({});
  const[s,setS]=useState<any[]>([]);
  const[n,setN]=useState<any>({});
  const[editing,setEditing]=useState<any|null>(null);
  const[msg,setMsg]=useState('');
  const[error,setError]=useState('');
  const[fieldErrors,setFieldErrors]=useState<Record<string,string>>({});
  const[improving,setImproving]=useState<string|null>(null);
  const[lastImprovement,setLastImprovement]=useState<Improvement|null>(null);
  const[assistance,setAssistance]=useState<Record<string,Assistance>>({});

  const load=()=>Promise.all([api.get('/company'),api.get('/company/services')]).then(([a,b])=>{setC(a.data.company||{});setS(b.data.services||[])});
  useEffect(()=>{load()},[]);

  const overallQuality=useMemo(()=>aggregateContextQuality(contextualFields.map(field=>({field:field.key,value:c[field.key],targetLength:field.targetLength,optional:field.optionalContext,weight:['description','target_audience','differentials'].includes(field.key)?1.35:1}))),[c]);
  const essentialFields=useMemo(()=>contextualFields.filter(field=>!field.optionalContext),[]);
  const readyFields=useMemo(()=>essentialFields.filter(field=>evaluateContext(c[field.key],field.targetLength,field.key).acceptable).length,[c,essentialFields]);

  const updateField=(field:CompanyField,value:string)=>{
    setC((current:any)=>({...current,[field.key]:value}));
    setFieldErrors(current=>({...current,[field.key]:validateField(field,value)}));
    setAssistance(current=>{const next={...current};delete next[field.key];return next});
  };
  const save=async(e:FormEvent)=>{
    e.preventDefault();setError('');
    const nextErrors:Record<string,string>={};
    for(const field of groups.flatMap(group=>group.fields)){const fieldError=validateField(field,c[field.key]);if(fieldError)nextErrors[field.key]=fieldError;}
    setFieldErrors(nextErrors);if(Object.keys(nextErrors).length){setError('Revise os campos destacados antes de salvar.');return;}
    try{await api.put('/company',c);setMsg('Contexto empresarial atualizado.');setTimeout(()=>setMsg(''),2500)}catch(e:any){setError(e.response?.data?.error||'Não foi possível salvar o contexto.')}
  };

  const improve=async(field:CompanyField)=>{
    const value=String(c[field.key]??'').trim();if(value.length<3)return;
    const before=evaluateContext(value,field.targetLength,field.key);
    setError('');setImproving(field.key);setLastImprovement(null);
    try{
      const r=await api.post('/company/context/improve',{field:field.key,value,draft_context:c});
      if(r.data?.accepted&&r.data?.text){
        updateField(field,r.data.text);
        setLastImprovement({field:field.key,before:value,after:r.data.text,beforeScore:r.data.previous_score??before.score,afterScore:r.data.score??100});
        setMsg('Campo aprimorado e validado para uso.');setTimeout(()=>setMsg(''),2600);
      }else{
        setAssistance(current=>({...current,[field.key]:{score:r.data?.score??before.score,missing:r.data?.missing||['adicione mais um detalhe real sobre a empresa']}}));
      }
    }catch(e:any){setError(e.response?.data?.error||'Não foi possível melhorar este campo agora.')}finally{setImproving(null)}
  };

  const undo=()=>{if(!lastImprovement)return;const field=contextualFields.find(f=>f.key===lastImprovement.field);if(field)updateField(field,lastImprovement.before);setLastImprovement(null);setMsg('Melhoria desfeita.');setTimeout(()=>setMsg(''),1800)};
  const add=async()=>{if(!n.name)return;await api.post('/company/services',n);setN({});await load();setMsg('Serviço adicionado.');setTimeout(()=>setMsg(''),1800)};
  const saveService=async()=>{if(!editing?.id||!editing.name)return;await api.put(`/company/services/${editing.id}`,editing);setEditing(null);await load();setMsg('Serviço atualizado.');setTimeout(()=>setMsg(''),1800)};

  return <div className="page company-page company-v4">
    <div className="page-head company-page-head"><div><span className="workspace-breadcrumb">Empresa <FiArrowRight/> Contexto</span><h1>Contexto empresarial</h1><p>Organize o conhecimento que os agentes usam para responder, criar e analisar sem precisar repetir as mesmas informações em cada tarefa.</p></div><div className="info-chip"><FiShield/> Compartilhado com os agentes</div></div>

    <section className="context-health-panel context-health-v4">
      <div className="context-health-main"><div className="context-health-icon"><FiBarChart2/></div><div><span className="panel-kicker">Prontidão do contexto</span><h2>{overallQuality.label}</h2><p>{overallQuality.acceptable?'Sua base já tem qualidade suficiente para orientar as ferramentas com consistência.':'Alguns campos essenciais ainda podem produzir respostas genéricas. Complete-os ou use a melhoria assistida.'}</p></div></div>
      <div className="context-health-score"><strong>{overallQuality.score}</strong><span>/100</span></div>
      <div className="context-health-progress"><i style={{width:`${overallQuality.score}%`}}/></div>
      <div className="context-health-foot"><span><FiCheckCircle/> {readyFields} de {essentialFields.length} campos essenciais prontos</span><span>Meta mínima de qualidade: {CONTEXT_ACCEPTABLE_SCORE}/100 por campo essencial</span></div>
    </section>

    {lastImprovement&&<div className="improvement-toast"><div><FiZap/><span><strong>Melhoria validada</strong><small>{lastImprovement.beforeScore}/100 → {lastImprovement.afterScore}/100. O texto foi aplicado ao campo.</small></span></div><button type="button" onClick={undo}><FiRotateCcw/> Desfazer</button></div>}
    {msg&&<div className="alert-box success"><FiCheck/> {msg}</div>}
    {error&&<div className="alert-box error"><FiAlertCircle/> {error}</div>}

    <form onSubmit={save} className="company-stack">
      {groups.map((group,index)=><section className="panel company-context-panel" key={group.title}>
        <div className="company-section-index">0{index+1}</div>
        <div className="panel-title company-panel-title"><div><span className="panel-kicker">{group.eyebrow}</span><h3>{group.title}</h3><p>{group.description}</p></div></div>
        <div className="form-grid company-form-grid">
          {group.fields.map(field=>{
            const value=c[field.key]||'';
            const quality=field.contextual?evaluateContext(value,field.targetLength,field.key):null;
            const canImprove=field.improvable&&quality&&!quality.acceptable&&String(value).trim().length>=3;
            const help=assistance[field.key];
            return <div className={`${field.span?'span-2 ':''}field context-field ${fieldErrors[field.key]?'invalid':''}`} key={field.key}>
              <div className="context-field-label"><label htmlFor={`company-${field.key}`}>{field.label}</label><Tooltip text={field.tooltip}/>{field.maxLength&&field.type==='textarea'&&<small>{String(value).length}/{field.maxLength}</small>}</div>
              {field.type==='textarea'?<textarea id={`company-${field.key}`} rows={4} maxLength={field.maxLength} placeholder={field.placeholder} value={value} onChange={e=>updateField(field,e.target.value)}/>:field.type==='tone'?<select id={`company-${field.key}`} value={value||'Profissional'} onChange={e=>updateField(field,e.target.value)}>{['Profissional','Amigável','Premium','Persuasivo','Casual','Formal'].map(o=><option key={o}>{o}</option>)}</select>:<input id={`company-${field.key}`} type={field.type==='email'?'email':'text'} maxLength={field.maxLength} placeholder={field.placeholder} value={value} onChange={e=>updateField(field,e.target.value)}/>} 
              {fieldErrors[field.key]&&<span className="field-error">{fieldErrors[field.key]}</span>}
              {quality&&<div className="context-field-footer"><QualityMeter quality={quality}/><span className="context-quality-hint">{quality.acceptable?'Pronto para orientar os agentes.':quality.missing[0]||quality.hint}</span>{canImprove&&<button type="button" className="context-ai-button" onClick={()=>improve(field)} disabled={improving===field.key}><FiZap/>{improving===field.key?'Analisando e reescrevendo…':'Melhorar com IA'}</button>}</div>}
              {help&&<div className="context-needs-input"><FiAlertCircle/><div><strong>A IA não vai inventar informações para elevar a nota.</strong><p>Para deixar este campo realmente pronto, acrescente:</p><ul>{help.missing.map(item=><li key={item}>{item}</li>)}</ul></div></div>}
            </div>
          })}
        </div>
      </section>)}
      <div className="sticky-save"><div><FiCheckCircle/><span>Os textos melhorados só são aplicados automaticamente quando passam pela validação de qualidade.</span></div><button className="btn-primary-custom"><FiCheck/> Salvar contexto</button></div>
    </form>

    <section className="panel mt-4 services-v4"><div className="panel-title company-panel-title"><div><span className="panel-kicker">Catálogo operacional</span><h3>Serviços</h3><p>Preços, duração e observações podem ser usados automaticamente nas respostas e propostas.</p></div></div>
      <div className="service-create service-create-detailed"><input placeholder="Nome do serviço" value={n.name||''} onChange={e=>setN({...n,name:e.target.value})}/><input placeholder="Descrição" value={n.description||''} onChange={e=>setN({...n,description:e.target.value})}/><input placeholder="Preço (R$)" type="number" step="0.01" value={n.price||''} onChange={e=>setN({...n,price:e.target.value})}/><input placeholder="Duração" value={n.duration||''} onChange={e=>setN({...n,duration:e.target.value})}/><input placeholder="Observações" value={n.notes||''} onChange={e=>setN({...n,notes:e.target.value})}/><button type="button" className="btn-primary-custom" onClick={add}><FiPlus/> Adicionar</button></div>
      <div className="service-list">{s.map(x=>editing?.id===x.id?<div className="service-editor" key={x.id}><div className="form-grid"><div className="field"><label>Nome</label><input value={editing.name||''} onChange={e=>setEditing({...editing,name:e.target.value})}/></div><div className="field"><label>Preço</label><input type="number" step="0.01" value={editing.price??''} onChange={e=>setEditing({...editing,price:e.target.value})}/></div><div className="field span-2"><label>Descrição</label><textarea rows={2} value={editing.description||''} onChange={e=>setEditing({...editing,description:e.target.value})}/></div><div className="field"><label>Duração</label><input value={editing.duration||''} onChange={e=>setEditing({...editing,duration:e.target.value})}/></div><div className="field"><label>Observações</label><input value={editing.notes||''} onChange={e=>setEditing({...editing,notes:e.target.value})}/></div></div><div className="service-editor-actions"><button className="btn-ghost" type="button" onClick={()=>setEditing(null)}><FiX/> Cancelar</button><button className="btn-primary-custom" type="button" onClick={saveService}><FiSave/> Salvar serviço</button></div></div>:<div className="service-row" key={x.id}><div><b>{x.name}</b><small>{x.description||'Sem descrição'}{x.duration?` · ${x.duration}`:''}{x.notes?` · ${x.notes}`:''}</small></div><strong>{x.price!==null&&x.price!==''?`R$ ${Number(x.price).toFixed(2).replace('.',',')}`:'Preço não informado'}</strong><button className="icon-btn" type="button" title="Editar" onClick={()=>setEditing({...x})}><FiEdit3/></button><button className="icon-btn danger" type="button" title="Excluir" onClick={async()=>{if(confirm('Excluir este serviço?')){await api.delete(`/company/services/${x.id}`);load()}}}><FiTrash2/></button></div>)}{!s.length&&<div className="empty">Nenhum serviço cadastrado ainda.</div>}</div>
    </section>
  </div>
}
