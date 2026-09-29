import {ChangeEvent,useEffect,useState} from 'react';
import {api} from '../api';
import {FiCheckCircle,FiDownload,FiFile,FiTrash2,FiUploadCloud} from 'react-icons/fi';

export default function FilesPage(){
  const[f,setF]=useState<any[]>([]);
  const[busy,setBusy]=useState(false);
  const[msg,setMsg]=useState('');
  const load=()=>api.get('/files').then(r=>setF(r.data.files));
  useEffect(()=>{load()},[]);
  const upload=async(e:ChangeEvent<HTMLInputElement>)=>{const file=e.target.files?.[0];if(!file)return;const fd=new FormData();fd.append('file',file);setBusy(true);setMsg('');try{const r=await api.post('/files/upload',fd);setMsg(r.data.extraction_note||'Arquivo enviado e processado com segurança.');load()}catch(x:any){setMsg(x.response?.data?.error||'Erro no upload.')}finally{setBusy(false);e.target.value=''}};
  return <div className="page"><div className="page-head"><div><span className="page-kicker">BASE DE DOCUMENTOS</span><h1>Arquivos</h1><p>Envie PDF, DOCX, TXT ou CSV para usar no agente de documentos.</p></div></div><label className="upload-box"><div className="upload-icon"><FiUploadCloud/></div><b>{busy?'Processando arquivo…':'Arraste ou selecione um documento'}</b><span>Extensão e MIME são validados no backend. Limite padrão: 10 MB.</span><span className="btn-ghost">Selecionar arquivo</span><input type="file" accept=".pdf,.docx,.txt,.csv" onChange={upload} hidden disabled={busy}/></label>{msg&&<div className="alert-box info mt-3">{msg}</div>}<section className="panel mt-4"><div className="panel-title"><div><span className="panel-kicker">ARMAZENAMENTO</span><h3>Seus documentos</h3></div><span>{f.length} arquivo(s)</span></div>{f.map(x=><div className="file-row" key={x.id}><div className="file-icon"><FiFile/></div><div><b>{x.original_name}</b><small>{String(x.extension).toUpperCase()} · {(x.size_bytes/1024).toFixed(1)} KB · {new Date(x.created_at).toLocaleString('pt-BR')}</small></div><span className={'extract-status '+(Number(x.extracted)?'ok':'warn')}>{Number(x.extracted)?<><FiCheckCircle/> Texto extraído</>:'Análise nativa disponível'}</span><a className="icon-btn" href={`/api/files/${x.id}/download`} title="Baixar arquivo"><FiDownload/></a><button className="icon-btn danger" title="Excluir" onClick={async()=>{if(confirm('Excluir este arquivo?')){await api.delete(`/files/${x.id}`);load()}}}><FiTrash2/></button></div>)}{!f.length&&<div className="empty">Nenhum arquivo enviado.</div>}</section></div>
}
