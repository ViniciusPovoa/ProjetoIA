import {FormEvent,useEffect,useMemo,useState} from 'react';
import {Link,useLocation,useParams} from 'react-router-dom';
import {api} from '../api';
import ProposalStudio from '../components/ProposalStudio';
import {
  FiArrowLeft,FiArrowRight,FiCheck,FiChevronRight,FiCopy,FiDownload,FiEdit3,FiFileText,FiImage,
  FiLayers,FiPlayCircle,FiRefreshCw,FiShield,FiStar,FiType,FiX,FiZap,FiInstagram,FiSend,FiExternalLink,FiLink2
} from 'react-icons/fi';

type Field={name:string;label:string;type:'text'|'textarea'|'select'|'file';required?:boolean;placeholder?:string;options?:string[]};
type Agent={id:number;slug:string;name:string;description:string;category:string;credit_cost:number;input_schema:Field[]};
type ContentType='carousel'|'reels'|'stories'|'publication'|'generic';
type ContentBlock={label:string;body:string};
type ContentSection={id:string;type:ContentType;title:string;summary:string;blocks:ContentBlock[];caption:string;hashtags:string[];cta:string;raw:string};
type ImageFormat='story'|'publication';
type GeneratedImage={image:string;format:ImageFormat;credits_used:number;provider:string;model:string;mime:string;media_url?:string;media_asset_id?:number;media_expires_in_hours?:number;media_publishable?:boolean;visual_brief?:string;prompt_source?:string;must_include?:string[];meta?:Record<string,unknown>};
type InstagramStatus={configured:boolean;connected:boolean;account?:{ig_user_id:string;username?:string;account_type?:string;token_expires_at?:string;last_publish_at?:string}|null};
type InstagramPublishResult={published:boolean;media_id:string;permalink?:string;username?:string;kind:'feed'|'story'};

const TYPE_META:Record<ContentType,{label:string;icon:typeof FiLayers}>={
  carousel:{label:'Carrossel',icon:FiLayers},
  reels:{label:'Reels',icon:FiPlayCircle},
  stories:{label:'Stories',icon:FiImage},
  publication:{label:'Publicação',icon:FiImage},
  generic:{label:'Conteúdo',icon:FiType},
};

const IMAGE_STYLES=['Arte publicitária editorial','Editorial clean','Minimalista sofisticado','Fotográfico premium','Lifestyle natural','Produto em destaque'];

function sanitizeVisualMarker(text:string):string{
  return text.replace(/\s*\(imagem(?:\s*:[^)]*)?\)\s*/gi,' ').replace(/^\s*:\s*/,'').replace(/\s{2,}/g,' ').trim();
}
function cleanLine(line:string):string{return sanitizeVisualMarker(line.replace(/^#+\s*/,'').replace(/^\d+[.)\-:]\s*/,'').trim());}
function detectType(text:string):ContentType{
  const normalized=text.toLowerCase();
  if(/\b(carrossel|carousel)\b/.test(normalized)) return 'carousel';
  if(/\b(reels?|roteiro|cena\s*\d+)\b/.test(normalized)) return 'reels';
  if(/\b(stories|story\s*\d+|tela\s*\d+)\b/.test(normalized)) return 'stories';
  if(/\b(publica(?:ção|cao)|post(?:agem)?|feed|texto para post)\b/.test(normalized)) return 'publication';
  return 'generic';
}
function isSectionHeading(line:string):boolean{
  const value=line.trim();
  if(!value) return false;
  if(/^(?:#{1,4}\s*)?\d+[.)]\s*(?:legenda\s+principal\b.*\b(?:carrossel|slides?)|texto\s+curto\b.*\b(?:post|feed)|sugest.*\bstories|legenda\s+para\s+reels?)\b/i.test(value)) return true;
  if(/^(?:#{1,4}\s*)?(?:post\s*\d+\s*[-–—:]\s*)?(carrossel|carousel|reels?|stories|publica(?:ção|cao)|feed)\b/i.test(value)) return true;
  if(/^(?:#{1,4}\s*)?\d+[.)]\s*(texto\s+para\s+post\s+(?:no\s+)?feed|carrossel|reels?|stories)\b/i.test(value)) return true;
  return false;
}
function splitMarketingSections(raw:string):string[]{
  const lines=raw.replace(/\r/g,'').split('\n');
  const sections:string[]=[];
  let current:string[]=[];
  let started=false;
  for(const original of lines){
    const heading=isSectionHeading(original);
    if(heading){
      if(current.some(line=>line.trim())) sections.push(current.join('\n').trim());
      current=[original];started=true;continue;
    }
    if(started) current.push(original);
  }
  if(current.some(line=>line.trim())) sections.push(current.join('\n').trim());
  if(sections.length) return sections;
  return raw.trim()?[raw.trim()]:[];
}
function parseBlocks(sectionRaw:string):Omit<ContentSection,'id'|'type'|'raw'>{
  const lines=sectionRaw.split('\n');
  const header=cleanLine(lines[0]||'Conteúdo');
  const bodyLines=lines.slice(1);
  const blockRegex=/^(?:[-*]\s*)?(card\s*\d+(?:\s*\([^)]*\))?|slide\s*\d+(?:\s*\([^)]*\))?|cena\s*\d+(?:\s*\([^)]*\))?|story\s*\d+(?:\s*\([^)]*\))?|tela\s*\d+(?:\s*\([^)]*\))?|legenda(?:\s+sugerida[^:]*)?|descrição\s+do\s+reels?|hashtags?(?:\s+sugeridas?)?|cta|chamada\s+para\s+ação)\s*[:\-–]?\s*(.*)$/i;
  const blocks:ContentBlock[]=[];
  let current:ContentBlock|null=null;
  for(const rawLine of bodyLines){
    const line=rawLine.trim();
    if(/^(?:roteiro|sequência|sequencia)\s*:$/i.test(line))continue;
    const auxiliary=line.match(/^(?:\d+[.)]\s*)?(?:sugest.*\s+de\s+)?(hashtags?|observa.*(?:pr.ticas)?)\s*:?-?\s*(.*)$/i);
    if(auxiliary){
      if(current&&current.body.trim()) blocks.push({...current,body:sanitizeVisualMarker(current.body.trim())});
      current={label:/^hashtag/i.test(cleanLine(auxiliary[1]))?'Hashtags':'Observações',body:sanitizeVisualMarker(auxiliary[2]||'')};
      continue;
    }
    const match=line.match(blockRegex);
    if(match){
      if(current&&current.body.trim()) blocks.push({...current,body:sanitizeVisualMarker(current.body.trim())});
      current={label:cleanLine(match[1]),body:sanitizeVisualMarker(match[2]||'')};
      continue;
    }
    if(current){current.body=current.body?`${current.body}\n${sanitizeVisualMarker(rawLine)}`:sanitizeVisualMarker(rawLine);}
    else if(line!==''){current={label:'Conteúdo',body:sanitizeVisualMarker(rawLine)};}
  }
  if(current&&current.body.trim()) blocks.push({...current,body:sanitizeVisualMarker(current.body.trim())});

  const captionBlock=blocks.find(block=>/^(legenda|descrição)/i.test(block.label));
  const hashtagBlock=blocks.find(block=>/^hashtags?/i.test(block.label));
  const ctaBlock=blocks.find(block=>/^(cta|chamada)/i.test(block.label));
  const display=blocks.filter(block=>!/^(legenda|descrição)/i.test(block.label)&&!/^hashtags?/i.test(block.label)&&!/^(cta|chamada)/i.test(block.label));
  const visualDisplay=display.filter(block=>!/^observa/i.test(block.label));
  const fallback=sanitizeVisualMarker(bodyLines.join('\n').trim());
  const usable=visualDisplay.length?visualDisplay:[{label:'Conteúdo',body:fallback||header}];
  const summary=(usable[0]?.body||header).split(/\n+/)[0].trim();
  const hashtags=((hashtagBlock?.body||sectionRaw).match(/#[\p{L}\p{N}_]+/gu)||[]).slice(0,15);
  return {title:header,summary,blocks:usable,caption:captionBlock?.body||'',hashtags,cta:ctaBlock?.body||''};
}
function buildMarketingSections(raw:string):ContentSection[]{
  const structured=parseStructuredMarketing(raw);
  if(structured?.length)return structured;
  return splitMarketingSections(raw).map((piece,index)=>{
    const headingType=detectType(piece.split('\n')[0]||'');
    const type=headingType==='generic'?detectType(piece):headingType;
    const parsed=parseBlocks(piece);
    return {id:`section-${index}`,type,raw:piece,...parsed};
  });
}

function parseStructuredMarketing(raw:string):ContentSection[]|null{
  try{
    const clean=raw.trim().replace(/^```(?:json)?\s*/i,'').replace(/\s*```$/,'');
    const data:any=JSON.parse(clean);
    if(!Array.isArray(data?.sections))return null;
    const allowed:ContentType[]=['carousel','reels','stories','publication'];
    const sections=data.sections.map((section:any,index:number):ContentSection|null=>{
      const requestedType=String(section?.type||'').toLowerCase() as ContentType;
      const type=allowed.includes(requestedType)?requestedType:'generic';
      const blocks=(Array.isArray(section?.blocks)?section.blocks:[]).map((block:any,blockIndex:number):ContentBlock=>({
        label:cleanLine(String(block?.label||`${type==='reels'?'Cena':type==='stories'?'Story':'Slide'} ${blockIndex+1}`)),
        body:sanitizeVisualMarker(String(block?.body||block?.text||'')),
      })).filter((block:ContentBlock)=>block.body!=='');
      if(!blocks.length)return null;
      const title=cleanLine(String(section?.title||TYPE_META[type].label));
      const caption=String(section?.caption||'').trim();
      const cta=String(section?.cta||'').trim();
      const hashtags=(Array.isArray(section?.hashtags)?section.hashtags:[]).map((tag:unknown)=>String(tag).trim()).filter(Boolean).slice(0,15);
      return {id:`section-${index}`,type,title,summary:blocks[0].body.split(/\n+/)[0],blocks,caption,hashtags,cta,raw:JSON.stringify(section)};
    }).filter((section:ContentSection|null):section is ContentSection=>section!==null);
    return sections.length?sections:null;
  }catch{return null;}
}
function downloadDataUri(uri:string,filename:string){
  const a=document.createElement('a');a.href=uri;a.download=filename;document.body.appendChild(a);a.click();a.remove();
}
function imageExtension(mime:string):string{
  if(mime==='image/svg+xml')return 'svg';
  if(mime==='image/png')return 'png';
  return 'jpg';
}
function compactLabel(text:string):string{return text.replace(/\s*\([^)]*\)\s*/g,'').replace(/^Conteúdo$/i,'Texto').trim();}

export default function AgentRunner(){
  const{id}=useParams();
  const loc=useLocation();
  const[agent,setAgent]=useState<Agent|null>(null);
  const[form,setForm]=useState<Record<string,unknown>>({});
  const[result,setResult]=useState('');
  const[runId,setRunId]=useState<number|null>(null);
  const[files,setFiles]=useState<Array<{id:number;original_name:string;extracted?:number}>>([]);
  const[loading,setLoading]=useState(false);
  const[error,setError]=useState('');
  const[copiedKey,setCopiedKey]=useState('');
  const[editing,setEditing]=useState(false);
  const[briefingOpen,setBriefingOpen]=useState(false);
  const[activeTab,setActiveTab]=useState<'overview'|ContentType>('overview');
  const[activeSectionId,setActiveSectionId]=useState('');
  const[activeBlockIndex,setActiveBlockIndex]=useState(0);
  const[imageFormat,setImageFormat]=useState<ImageFormat>('story');
  const[imageStyle,setImageStyle]=useState(IMAGE_STYLES[0]);
  const[imageLoadingKey,setImageLoadingKey]=useState('');
  const[testImageLoading,setTestImageLoading]=useState(false);
  const[imageVersions,setImageVersions]=useState<Record<string,GeneratedImage[]>>({});
  const[selectedImageVersion,setSelectedImageVersion]=useState<Record<string,number>>({});
  const[visualDirections,setVisualDirections]=useState<Record<string,string>>({});
  const[instagramStatus,setInstagramStatus]=useState<InstagramStatus>({configured:false,connected:false});
  const[instagramLoading,setInstagramLoading]=useState(false);
  const[publishOpen,setPublishOpen]=useState(false);
  const[publishKind,setPublishKind]=useState<'feed'|'story'>('feed');
  const[publishCaption,setPublishCaption]=useState('');
  const[publishLoading,setPublishLoading]=useState(false);
  const[publishResult,setPublishResult]=useState<InstagramPublishResult|null>(null);

  const apiOrigin=useMemo(()=>{try{return new URL(String(api.defaults.baseURL||'/api'),window.location.origin).origin;}catch{return window.location.origin;}},[]);

  const schema=useMemo(()=>agent?.input_schema||[],[agent]);
  const isMarketing=agent?.slug==='marketing';
  const sections=useMemo(()=>isMarketing?buildMarketingSections(result):[],[isMarketing,result]);
  const availableTypes=useMemo(()=>Array.from(new Set(sections.map(section=>section.type))),[sections]);
  const activeSections=useMemo(()=>activeTab==='overview'?sections:sections.filter(section=>section.type===activeTab),[sections,activeTab]);
  const activeSection=useMemo(()=>activeSections.find(section=>section.id===activeSectionId)||activeSections[0]||null,[activeSections,activeSectionId]);
  const activeBlock=activeSection?.blocks[activeBlockIndex]||activeSection?.blocks[0]||null;
  const assetKey=activeSection&&activeBlock?`${activeSection.id}-${activeBlockIndex}`:'';
  const versions=assetKey?(imageVersions[assetKey]||[]):[];
  const versionIndex=assetKey?(selectedImageVersion[assetKey]??Math.max(0,versions.length-1)):0;
  const currentImage=versions[versionIndex];
  const generatedCaption=useMemo(()=>{if(!activeSection||!activeBlock)return '';const parts:string[]=[];const main=(activeSection.caption||activeBlock.body||'').trim();if(main)parts.push(main);if(activeSection.cta&&activeSection.cta.trim()&&!main.includes(activeSection.cta.trim()))parts.push(activeSection.cta.trim());if(activeSection.hashtags.length)parts.push(activeSection.hashtags.join(' '));return parts.join('\n\n').slice(0,2200);},[activeSection,activeBlock]);

  const campaignTitle=useMemo(()=>{
    const candidates=['theme','tema','topic','subject','assunto','title','objective','objetivo'];
    for(const key of candidates){const value=String(form[key]??'').trim();if(value)return value;}
    return 'Campanha de marketing';
  },[form]);
  const briefingItems=useMemo(()=>schema.map(field=>({label:field.label,value:String(form[field.name]??'').trim()})).filter(item=>item.value).slice(0,4),[schema,form]);
  const historyRunId=useMemo(()=>{
    const value=new URLSearchParams(loc.search).get('run');
    return value&&/^\d+$/.test(value)?Number(value):null;
  },[loc.search]);

  useEffect(()=>{
    setResult('');setError('');setRunId(null);setImageVersions({});setVisualDirections({});setEditing(false);setBriefingOpen(false);setActiveTab('overview');
    const historyRequest=historyRunId?api.get(`/history/${historyRunId}`):Promise.resolve(null);
    Promise.all([api.get(`/agents/${id}`),api.get('/files'),historyRequest]).then(([a,f,h])=>{
      const loaded=a.data.agent as Agent;setAgent(loaded);setFiles(f.data.files||[]);
      const restored=h?.data?.item;
      if(restored&&Number(restored.agent_id)===Number(loaded.id)){
        setForm(restored.input||{});setResult(String(restored.output_text||''));setRunId(Number(restored.id));setEditing(false);setBriefingOpen(false);setActiveTab('overview');setImageStyle(IMAGE_STYLES[0]);return;
      }
      const reusable=(loc.state as {input?:Record<string,unknown>}|null)?.input;
      if(reusable)setForm(reusable);else{
        const defaults:Record<string,unknown>={};
        (loaded.input_schema||[]).forEach(field=>{if(field.type==='select'&&field.options?.length)defaults[field.name]=field.options[0];});
        setForm(defaults);
      }
    }).catch((e:any)=>setError(e.response?.data?.error||'Não foi possível carregar a ferramenta.'));
  },[id,loc.state,historyRunId]);

  useEffect(()=>{
    if(activeTab!=='overview'&&!activeSections.some(section=>section.id===activeSectionId)){
      setActiveSectionId(activeSections[0]?.id||'');setActiveBlockIndex(0);
    }
  },[activeTab,activeSections,activeSectionId]);

  const loadInstagramStatus=async()=>{try{const response=await api.get('/integrations/instagram');setInstagramStatus(response.data as InstagramStatus);}catch{/* integração opcional */}};
  useEffect(()=>{if(agent?.slug==='marketing')void loadInstagramStatus();},[agent?.slug]);
  useEffect(()=>{
    const handler=(event:MessageEvent)=>{if(event.origin!==apiOrigin)return;if(event.data?.type==='equipeai:instagram-oauth'){if(event.data?.ok)void loadInstagramStatus();else setError(event.data?.message||'Não foi possível conectar o Instagram.');}};
    window.addEventListener('message',handler);return()=>window.removeEventListener('message',handler);
  },[apiOrigin]);

  const run=async(e?:FormEvent)=>{
    e?.preventDefault();setLoading(true);setError('');
    try{
      const response=await api.post(`/agents/${id}/execute`,form);
      setResult(response.data.result as string);setRunId(response.data.run_id as number);setEditing(false);setBriefingOpen(false);setActiveTab('overview');setImageStyle(IMAGE_STYLES[0]);setImageVersions({});setVisualDirections({});
    }catch(x:any){setError(x.response?.data?.error||'Não foi possível gerar a resposta. Seus créditos não foram consumidos.');}
    finally{setLoading(false);}
  };
  const copy=async(text:string,key:string)=>{await navigator.clipboard.writeText(text);setCopiedKey(key);window.setTimeout(()=>setCopiedKey(''),1400);};
  const openType=(type:ContentType,sectionId?:string)=>{setActiveTab(type);setActiveSectionId(sectionId||sections.find(s=>s.type===type)?.id||'');setActiveBlockIndex(0);};

  const generateImage=async()=>{
    if(!agent||!activeSection||!activeBlock||!assetKey)return;
    setImageLoadingKey(assetKey);setError('');
    try{
      const response=await api.post(`/agents/${agent.id}/generate-image`,{
        format:imageFormat,style:imageStyle,title:activeSection.title,summary:activeBlock.body.split(/\n+/)[0],body:activeBlock.body,
        platform:String(form.platform||'Instagram'),objective:String(form.objective||form.objetivo||''),audience:String(form.audience||form.publico||form['público']||''),
        tone:String(form.tone||form.tom||''),additional_info:String(form.additional_info||form.notes||''),content_type:activeSection.type,
        visual_direction:String(visualDirections[assetKey]||''),block_label:activeBlock.label,block_index:activeBlockIndex+1,
      });
      const img=response.data as GeneratedImage;
      if(img.visual_brief) setVisualDirections(current=>({...current,[assetKey]:img.visual_brief||''}));
      setImageVersions(current=>{const next=[...(current[assetKey]||[]),img];return {...current,[assetKey]:next};});
      setSelectedImageVersion(current=>({...current,[assetKey]:(imageVersions[assetKey]?.length||0)}));
    }catch(x:any){setError(x.response?.data?.error||'Não foi possível gerar a imagem agora.');}
    finally{setImageLoadingKey('');}
  };

  const useTestImage=async()=>{
    if(!assetKey)return;
    setTestImageLoading(true);setError('');
    try{
      const response=await api.post('/integrations/instagram/test-media',{format:imageFormat});
      const img=response.data as GeneratedImage;
      setImageVersions(current=>{
        const next=[...(current[assetKey]||[]),img];
        setSelectedImageVersion(selected=>({...selected,[assetKey]:next.length-1}));
        return {...current,[assetKey]:next};
      });
    }catch(x:any){setError(x.response?.data?.error||'Nao foi possivel carregar a foto de teste agora.');}
    finally{setTestImageLoading(false);}
  };

  const connectInstagram=async()=>{
    setInstagramLoading(true);setError('');
    try{const response=await api.post('/integrations/instagram/connect',{return_to:window.location.pathname});const url=String(response.data.url||'');if(!url)throw new Error('URL de conexão ausente');const width=640,height=760,left=Math.max(0,(window.screen.width-width)/2),top=Math.max(0,(window.screen.height-height)/2);const popup=window.open(url,'equipeai-instagram',`width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`);if(!popup)window.location.href=url;}catch(x:any){setError(x.response?.data?.error||'Não foi possível iniciar a conexão com o Instagram.');}
    finally{setInstagramLoading(false);}
  };
  const disconnectInstagram=async()=>{if(!confirm('Desconectar esta conta do Instagram?'))return;setInstagramLoading(true);try{await api.delete('/integrations/instagram');setInstagramStatus({configured:true,connected:false});}catch(x:any){setError(x.response?.data?.error||'Não foi possível desconectar o Instagram.');}finally{setInstagramLoading(false);}};
  const openInstagramPublish=()=>{if(!currentImage)return;setPublishKind(currentImage.format==='story'?'story':'feed');setPublishCaption(generatedCaption);setPublishResult(null);setPublishOpen(true);};
  const publishInstagram=async()=>{
    if(!currentImage?.media_url)return;setPublishLoading(true);setError('');
    try{const response=await api.post('/integrations/instagram/publish',{media_url:currentImage.media_url,caption:publishKind==='feed'?publishCaption:'',kind:publishKind,alt_text:activeBlock?.body?.slice(0,900)||''});setPublishResult(response.data as InstagramPublishResult);void loadInstagramStatus();}
    catch(x:any){setError(x.response?.data?.error||'Não foi possível publicar no Instagram.');}
    finally{setPublishLoading(false);}
  };

  const renderFields=()=>schema.map(field=><div className="field" key={field.name}>
    <label>{field.label}{field.required&&<b className="required">*</b>}</label>
    {field.type==='textarea'?<textarea rows={field.name==='message'||field.name==='content'?7:4} value={String(form[field.name]??'')} placeholder={field.placeholder||''} required={field.required} onChange={e=>setForm({...form,[field.name]:e.target.value})}/>
    :field.type==='select'?<select value={String(form[field.name]??'')} required={field.required} onChange={e=>setForm({...form,[field.name]:e.target.value})}>{!field.required&&<option value="">Selecione</option>}{field.options?.map(option=><option key={option}>{option}</option>)}</select>
    :field.type==='file'?<div><select value={String(form[field.name]??'')} required={field.required} onChange={e=>setForm({...form,[field.name]:Number(e.target.value)})}><option value="">Selecione um arquivo</option>{files.map(file=><option key={file.id} value={file.id}>{file.original_name}{!Number(file.extracted)?' — sem texto extraído':''}</option>)}</select><small className="field-help"><FiFileText/> Envie arquivos em <Link to="/app/arquivos">Arquivos</Link> antes de usar.</small></div>
    :<input value={String(form[field.name]??'')} placeholder={field.placeholder||''} required={field.required} onChange={e=>setForm({...form,[field.name]:e.target.value})}/>}</div>);

  const renderForm=()=> <form className="panel runner-form" onSubmit={run}>
    <div className="panel-title"><div><span className="panel-kicker">Briefing da tarefa</span><h3>O que você precisa</h3><p>Preencha somente o necessário. O restante vem do contexto da empresa.</p></div></div>
    {renderFields()}
    {error&&<div className="alert-box error">{error}</div>}
    <button className="btn-primary-custom big w-100" disabled={loading}>{loading?<><span className="spinner"/> Preparando resultado…</>:<><FiZap/> Executar tarefa</>}</button>
    <small className="form-note"><FiShield/> Se a execução falhar, os créditos reservados são devolvidos automaticamente.</small>
  </form>;

  if(!agent)return <div className="page"><div className="panel empty large">{error||'Carregando ferramenta…'}</div></div>;


  const isCommercialProposal=agent.slug==='comercial'&&String(form.objective??'').toLowerCase()==='proposta';

  if(isCommercialProposal&&result){
    return <div className="page proposal-page-v37"><div className="proposal-page-topline-v37"><Link to="/app/agentes" className="back-link"><FiArrowLeft/> Ferramentas</Link><span>Comercial <FiChevronRight/> Proposta</span></div><ProposalStudio result={result} setResult={setResult} runId={runId} form={form} onRegenerate={()=>run()} loading={loading}/></div>;
  }

  if(isMarketing&&result){
    return <div className="page campaign-page-v34">
      <div className="campaign-page-topline"><Link to="/app/agentes" className="back-link"><FiArrowLeft/> Ferramentas</Link><span>Marketing <FiChevronRight/> Resultado</span></div>

      <section className="campaign-hero-v34">
        <div><span className="campaign-eyebrow">Campanha gerada</span><h1>{campaignTitle}</h1><div className="campaign-meta-v34">{briefingItems.slice(0,3).map(item=><span key={item.label}>{item.value}</span>)}<span>{sections.length} formatos</span></div></div>
        <div className="campaign-actions-v34"><button type="button" onClick={()=>copy(result,'all')}>{copiedKey==='all'?<FiCheck/>:<FiCopy/>} Copiar tudo</button><button type="button" onClick={()=>setEditing(v=>!v)}><FiEdit3/> {editing?'Fechar edição':'Editar texto'}</button><button type="button" onClick={()=>run()} disabled={loading}><FiRefreshCw/> Regenerar</button>{runId&&<button type="button" onClick={()=>api.patch(`/history/${runId}`,{favorite:true})}><FiStar/> Salvar</button>}</div>
      </section>

      <button type="button" className="briefing-strip-v34" onClick={()=>setBriefingOpen(true)}><span><strong>Briefing utilizado</strong>{briefingItems.map(item=><em key={item.label}>{item.value}</em>)}</span><b>Editar briefing <FiArrowRight/></b></button>
      {error&&<div className="alert-box error campaign-alert-v34">{error}</div>}

      {editing?<section className="campaign-raw-editor-v34"><div><span className="campaign-eyebrow">Edição manual</span><h2>Texto bruto da geração</h2><p>Edite o resultado original. Ao fechar, a estrutura visual é recalculada automaticamente.</p></div><textarea value={result} onChange={e=>setResult(e.target.value)} rows={26}/><button type="button" className="btn-primary-custom" onClick={()=>setEditing(false)}><FiCheck/> Aplicar alterações</button></section>
      :<>
        <nav className="campaign-tabs-v34" aria-label="Formatos da campanha">
          <button type="button" className={activeTab==='overview'?'active':''} onClick={()=>setActiveTab('overview')}>Visão geral</button>
          {availableTypes.map(type=><button type="button" key={type} className={activeTab===type?'active':''} onClick={()=>openType(type)}>{TYPE_META[type].label}<span>{sections.filter(section=>section.type===type).reduce((sum,section)=>sum+section.blocks.length,0)}</span></button>)}
        </nav>

        {activeTab==='overview'?<section className="campaign-overview-v34">
          <div className="overview-intro-v34"><span className="campaign-eyebrow">Plano de conteúdo</span><h2>{sections.length} formatos preparados para esta campanha.</h2><p>Abra um formato para revisar a sequência, ajustar o conteúdo e gerar as peças visuais.</p></div>
          <div className="editorial-list-v34">
            {sections.map((section,index)=>{const MetaIcon=TYPE_META[section.type].icon;return <button type="button" key={section.id} onClick={()=>openType(section.type,section.id)} className="editorial-row-v34"><span className="editorial-index-v34">0{index+1}</span><span className="editorial-icon-v34"><MetaIcon/></span><span className="editorial-copy-v34"><small>{TYPE_META[section.type].label}</small><strong>{section.summary||section.title}</strong><em>{section.blocks.length} {section.type==='reels'?'cenas':section.type==='stories'?'telas':section.type==='carousel'?'cards':'bloco'}</em></span><FiArrowRight/></button>;})}
          </div>
        </section>
        :activeSection&&activeBlock?<section className="campaign-canvas-v34">
          <main className="content-editor-v34">
            <div className="content-editor-head-v34"><div><span className="campaign-eyebrow">{TYPE_META[activeSection.type].label}</span><h2>{activeSection.title||TYPE_META[activeSection.type].label}</h2></div><span>{activeSection.blocks.length} {activeSection.type==='reels'?'cenas':activeSection.type==='stories'?'telas':activeSection.type==='carousel'?'cards':'bloco'}</span></div>

            {activeSection.blocks.length>1&&<div className={`sequence-nav-v34 type-${activeSection.type}`}>
              {activeSection.blocks.map((block,index)=><button type="button" key={`${activeSection.id}-${index}`} className={activeBlockIndex===index?'active':''} onClick={()=>setActiveBlockIndex(index)}>
                <span>{String(index+1).padStart(2,'0')}</span><strong>{compactLabel(block.label)}</strong><p>{block.body.split(/\n+/)[0]}</p>{activeSection.type==='reels'&&<em>{block.label.match(/\([^)]*\)/)?.[0]?.replace(/[()]/g,'')||'cena'}</em>}
              </button>)}
            </div>}

            <article className="editor-sheet-v34">
              <header><div><span>{compactLabel(activeBlock.label)}</span><strong>{String(activeBlockIndex+1).padStart(2,'0')} / {String(activeSection.blocks.length).padStart(2,'0')}</strong></div><button type="button" onClick={()=>copy(activeBlock.body,'block')}>{copiedKey==='block'?<FiCheck/>:<FiCopy/>} Copiar</button></header>
              <div className="editor-sheet-copy-v34">{activeBlock.body}</div>
            </article>

            {(activeSection.caption||activeSection.cta||activeSection.hashtags.length>0)&&<div className="content-properties-v34">
              {activeSection.caption&&<div><span>Legenda</span><p>{activeSection.caption}</p><button type="button" onClick={()=>copy(activeSection.caption,'caption')}>{copiedKey==='caption'?<FiCheck/>:<FiCopy/>}</button></div>}
              {activeSection.cta&&<div><span>CTA</span><p>{activeSection.cta}</p><button type="button" onClick={()=>copy(activeSection.cta,'cta')}>{copiedKey==='cta'?<FiCheck/>:<FiCopy/>}</button></div>}
              {activeSection.hashtags.length>0&&<div><span>Hashtags</span><p className="hashtag-line-v34">{activeSection.hashtags.join(' ')}</p><button type="button" onClick={()=>copy(activeSection.hashtags.join(' '),'hashtags')}>{copiedKey==='hashtags'?<FiCheck/>:<FiCopy/>}</button></div>}
            </div>}
          </main>

          <aside className="visual-inspector-v34">
            <div className="visual-inspector-head-v34"><div><span className="campaign-eyebrow">Visual</span><h3>{currentImage?'Peça gerada':'Criar peça visual'}</h3></div>{currentImage&&<span className="visual-ready-v34"><FiCheck/> Pronta</span>}</div>
            <div className={`visual-stage-v34 format-${imageFormat}`}>
              {currentImage?<img src={currentImage.image} alt="Imagem gerada para o conteúdo selecionado"/>:<div><FiImage/><strong>Sem imagem</strong><p>Gere uma peça específica para este {compactLabel(activeBlock.label).toLowerCase()}.</p></div>}
              {imageLoadingKey===assetKey&&<div className="visual-loading-v34"><span className="spinner"/><strong>Gerando visual…</strong></div>}
            </div>

            <div className="visual-control-v34"><label>Formato</label><div className="format-switch-v34"><button type="button" className={imageFormat==='story'?'active':''} onClick={()=>setImageFormat('story')}>Story <small>9:16</small></button><button type="button" className={imageFormat==='publication'?'active':''} onClick={()=>setImageFormat('publication')}>Feed <small>4:5</small></button></div></div>
            <div className="visual-control-v34"><label htmlFor="image-style">Estilo visual</label><select id="image-style" value={imageStyle} onChange={e=>setImageStyle(e.target.value)}>{IMAGE_STYLES.map(style=><option key={style}>{style}</option>)}</select></div>
            <div className="visual-control-v34 visual-direction-v35"><label htmlFor="visual-direction">Direção visual</label><textarea id="visual-direction" rows={4} value={visualDirections[assetKey]||''} onChange={e=>setVisualDirections(current=>({...current,[assetKey]:e.target.value}))} placeholder="Deixe em branco para criar uma arte publicitária com headline e layout editorial. Depois da primeira geração, você pode ajustar esta direção e criar outra versão."/><small>O sistema transforma o conteúdo selecionado em uma peça 2D com tipografia, sem render 3D. Você pode editar a direção antes de gerar outra versão.</small></div>

            <button type="button" className="visual-generate-v34" onClick={generateImage} disabled={imageLoadingKey===assetKey}><FiZap/> {currentImage?'Gerar nova versão':'Gerar imagem'}</button>
            <small className="visual-cost-note-v34">A Cloudflare gera somente a foto ilustrativa; o sistema aplica o texto vetorial exato e monta o layout editorial.</small>
            <button type="button" className="visual-test-image-v391" onClick={useTestImage} disabled={testImageLoading}><FiImage/> {testImageLoading?'Carregando foto...':'Usar foto aleatoria para teste'}</button>

            {currentImage&&<div className="visual-actions-v34"><button type="button" onClick={()=>downloadDataUri(currentImage.image,`equipeai-${activeSection.type}-${activeBlockIndex+1}.${imageExtension(currentImage.mime)}`)}><FiDownload/> Baixar</button><button type="button" onClick={generateImage}><FiRefreshCw/> Nova versão</button></div>}
            <div className="instagram-publisher-v36">
              <div className="instagram-publisher-head-v36"><span className="instagram-mark-v36"><FiInstagram/></span><div><strong>Instagram</strong><small>{instagramStatus.connected?`@${instagramStatus.account?.username||'conta conectada'}`:'Publique sem sair do EquipeAI'}</small></div>{instagramStatus.connected&&<span className="instagram-connected-v36"><i/> Conectado</span>}</div>
              {!instagramStatus.configured?<p className="instagram-note-v36">Configure o app da Meta no backend para habilitar a publicação.</p>
              :!instagramStatus.connected?<button type="button" className="instagram-connect-v36" onClick={connectInstagram} disabled={instagramLoading}><FiLink2/> {instagramLoading?'Abrindo Instagram…':'Conectar Instagram'}</button>
              :<><button type="button" className="instagram-publish-v36" onClick={openInstagramPublish} disabled={!currentImage?.media_url||currentImage?.media_publishable===false}><FiSend/> Publicar no Instagram</button><button type="button" className="instagram-disconnect-v36" onClick={disconnectInstagram} disabled={instagramLoading}>Desconectar conta</button>{(!currentImage?.media_url||currentImage?.media_publishable===false)&&<small className="instagram-note-v36">Para publicar, gere uma imagem raster compatível com o Instagram. Peças editoriais SVG exigem Imagick no servidor para conversão automática.</small>}</>}
            </div>
            {versions.length>1&&<div className="version-strip-v34"><span>Versões</span><div>{versions.map((_,index)=><button type="button" key={index} className={versionIndex===index?'active':''} onClick={()=>setSelectedImageVersion(current=>({...current,[assetKey]:index}))}>v{index+1}</button>)}</div></div>}
          </aside>
        </section>:null}
      </>}

      {publishOpen&&currentImage&&<div className="instagram-modal-layer-v36" role="dialog" aria-modal="true">
        <button className="instagram-modal-backdrop-v36" aria-label="Fechar" onClick={()=>!publishLoading&&setPublishOpen(false)}/><section className="instagram-modal-v36">
          <header><div><span className="instagram-modal-icon-v36"><FiInstagram/></span><div><span className="campaign-eyebrow">Publicação social</span><h2>Publicar no Instagram</h2><p>{instagramStatus.account?.username?`@${instagramStatus.account.username}`:'Conta conectada'}</p></div></div><button type="button" onClick={()=>setPublishOpen(false)} disabled={publishLoading}><FiX/></button></header>
          {publishResult?<div className="instagram-success-v36"><span><FiCheck/></span><h3>Publicado com sucesso</h3><p>O conteúdo foi enviado para @{publishResult.username||instagramStatus.account?.username||'Instagram'}.</p>{publishResult.permalink&&<a href={publishResult.permalink} target="_blank" rel="noreferrer">Abrir no Instagram <FiExternalLink/></a>}<button type="button" className="btn-primary-custom" onClick={()=>setPublishOpen(false)}>Concluir</button></div>
          :<div className="instagram-compose-v36">
            <div className="instagram-preview-v36"><img src={currentImage.image} alt="Prévia da publicação no Instagram"/><span>{publishKind==='story'?'Story 9:16':'Feed'}</span></div>
            <div className="instagram-compose-fields-v36">
              <div className="visual-control-v34"><label>Destino</label><div className="format-switch-v34"><button type="button" className={publishKind==='feed'?'active':''} onClick={()=>setPublishKind('feed')}>Feed <small>com legenda</small></button><button type="button" className={publishKind==='story'?'active':''} onClick={()=>setPublishKind('story')}>Story <small>imagem</small></button></div></div>
              <div className="instagram-caption-field-v36"><div><label htmlFor="instagram-caption">Legenda</label><span>{publishCaption.length}/2200</span></div><textarea id="instagram-caption" rows={10} value={publishCaption} maxLength={2200} disabled={publishKind==='story'} onChange={e=>setPublishCaption(e.target.value)} />{publishKind==='story'?<small>Stories publicados pela API não exibem esta legenda. Se quiser usar a descrição gerada, publique no Feed.</small>:<small>A descrição, CTA e hashtags da campanha já foram preenchidos. Revise antes de publicar.</small>}</div>
              <div className="instagram-confirm-v36"><FiShield/><p><strong>Você controla a publicação.</strong><span>Nada é postado automaticamente. Só publicamos depois da confirmação abaixo.</span></p></div>
              <button type="button" className="instagram-final-publish-v36" onClick={publishInstagram} disabled={publishLoading||!currentImage.media_url||currentImage.media_publishable===false}><FiSend/> {publishLoading?'Publicando…':publishKind==='story'?'Publicar Story agora':'Publicar no Feed agora'}</button>
            </div>
          </div>}
        </section>
      </div>}

      {briefingOpen&&<div className="briefing-drawer-layer-v34" role="dialog" aria-modal="true"><button className="briefing-drawer-backdrop-v34" aria-label="Fechar" onClick={()=>setBriefingOpen(false)}/><aside className="briefing-drawer-v34"><div className="briefing-drawer-head-v34"><div><span className="campaign-eyebrow">Briefing</span><h2>Editar campanha</h2><p>Altere os dados e execute novamente para recriar o conteúdo.</p></div><button type="button" onClick={()=>setBriefingOpen(false)}><FiX/></button></div><form onSubmit={run}>{renderFields()}{error&&<div className="alert-box error">{error}</div>}<button className="btn-primary-custom big w-100" disabled={loading}>{loading?'Gerando novamente…':<><FiRefreshCw/> Gerar novamente</>}</button></form></aside></div>}
    </div>;
  }

  return <div className="page runner-v4 runner-v5">
    <div className="runner-head-v4"><Link to="/app/agentes" className="back-link"><FiArrowLeft/> Ferramentas</Link><div className="page-head"><div><span className="workspace-breadcrumb">Ferramentas <FiArrowRight/> {agent.name}</span><h1>{agent.name}</h1><p>{agent.description}</p><div className="runner-trust-line"><span><FiShield/> Contexto empresarial incluído</span><span><FiZap/> {agent.credit_cost} crédito{agent.credit_cost>1?'s':''} por execução</span></div></div></div></div>
    <div className="runner-grid runner-grid-v4">
      {renderForm()}
      <section className={`panel result-panel ${loading?'is-generating':''}`}><div className="panel-title"><div><span className="panel-kicker">Saída</span><h3>{loading?'Processando tarefa':'Resultado'}</h3><p>{result?'Revise, copie ou edite antes de usar.':'O resultado aparece aqui sem tirar você do fluxo.'}</p></div>{result&&<div className="result-actions"><button title="Copiar" onClick={()=>copy(result,'all')}>{copiedKey==='all'?<FiCheck/>:<FiCopy/>}</button><button title="Editar" onClick={()=>setEditing(v=>!v)}><FiEdit3/></button><button title="Regenerar" onClick={()=>run()} disabled={loading}><FiRefreshCw/></button>{runId&&<button title="Favoritar" onClick={()=>api.patch(`/history/${runId}`,{favorite:true})}><FiStar/></button>}</div>}</div>
        {loading?<div className="generation-state"><span className="generation-pulse"/><div><strong>Usando seu briefing e contexto empresarial</strong><p>Aguarde enquanto a resposta é preparada.</p></div></div>:result?editing?<textarea className="result-editor" rows={18} value={result} onChange={e=>setResult(e.target.value)}/>:<div className="result-text">{result}</div>:<div className="result-empty"><div className="result-orb"><FiZap/></div><h4>Pronto para começar</h4><p>Preencha o briefing ao lado e execute a tarefa.</p></div>}
      </section>
    </div>
  </div>;
}
