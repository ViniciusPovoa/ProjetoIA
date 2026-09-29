import {useEffect, useState} from 'react';
import {Link} from 'react-router-dom';
import {FiCopy, FiExternalLink, FiRefreshCw, FiStar, FiTrash2} from 'react-icons/fi';
import {api} from '../api';

function itemTitle(item: any) {
  const input = item.input || {};
  return input.message || input.theme || input.task || input.service || input.review || 'Execução do agente';
}

function resultPreview(item: any) {
  const output = String(item.output_text || '');
  if (!output.trim().startsWith('{')) return output || 'Execução sem resposta.';

  try {
    const parsed = JSON.parse(output);
    const campaignTitle = parsed.campaign_title || parsed.title;
    const sectionTitles = Array.isArray(parsed.sections)
      ? parsed.sections.map((section: any) => section.title).filter(Boolean).slice(0, 3)
      : [];
    return [campaignTitle, ...sectionTitles].filter(Boolean).join('\n') || output;
  } catch {
    return output;
  }
}

export default function History() {
  const [history, setHistory] = useState<any[]>([]);
  const [pagination, setPagination] = useState<any>({page: 1, pages: 1, total: 0});
  const [page, setPage] = useState(1);
  const [favoritesOnly, setFavoritesOnly] = useState(false);

  const load = () => api.get('/history', {
    params: {page, per_page: 15, favorites: favoritesOnly ? 1 : 0},
  }).then((response) => {
    setHistory(response.data.history);
    setPagination(response.data.pagination);
  });

  useEffect(() => {
    load();
  }, [page, favoritesOnly]);

  return <div className="page">
    <div className="page-head">
      <div>
        <span className="page-kicker">MEMÓRIA DE TRABALHO</span>
        <h1>Histórico</h1>
        <p>Abra, copie, favorite ou reutilize resultados anteriores.</p>
      </div>
      <button
        className={'btn-filter ' + (favoritesOnly ? 'active' : '')}
        onClick={() => { setFavoritesOnly(!favoritesOnly); setPage(1); }}
      >
        <FiStar/> Somente favoritos
      </button>
    </div>

    <div className="history-list">
      {history.map((item) => <article className="panel history-card" key={item.id}>
        <div className="history-main">
          <div className="history-meta">
            <span>{item.agent_name}</span><i>•</i>
            <span>{new Date(item.created_at).toLocaleString('pt-BR')}</span>
          </div>
          <h3>{itemTitle(item)}</h3>
          <div className="result-text compact">{resultPreview(item)}</div>
          <div className="history-foot">
            <span>{item.provider_name} · {item.model_name}</span>
            <span>{item.credits_used} cr. · {(item.duration_ms / 1000).toFixed(1)}s</span>
          </div>
        </div>

        <div className="history-actions">
          <Link
            className="history-open-action"
            to={`/app/agentes/${item.agent_id}?run=${item.id}`}
            title={item.agent_slug === 'marketing' ? 'Abrir campanha' : 'Abrir resultado'}
            aria-label={item.agent_slug === 'marketing' ? 'Abrir campanha' : 'Abrir resultado'}
          >
            <FiExternalLink/><span>{item.agent_slug === 'marketing' ? 'Abrir campanha' : 'Abrir'}</span>
          </Link>
          <button onClick={() => navigator.clipboard.writeText(item.output_text || '')} title="Copiar"><FiCopy/></button>
          <Link to={`/app/agentes/${item.agent_id}`} state={{input: item.input}} title="Reutilizar"><FiRefreshCw/></Link>
          <button
            className={Number(item.favorite) ? 'active' : ''}
            onClick={async () => {
              await api.patch(`/history/${item.id}`, {favorite: !Number(item.favorite)});
              load();
            }}
            title="Favoritar"
          ><FiStar/></button>
          <button
            className="danger"
            onClick={async () => {
              if (confirm('Excluir este item do histórico?')) {
                await api.delete(`/history/${item.id}`);
                load();
              }
            }}
            title="Excluir"
          ><FiTrash2/></button>
        </div>
      </article>)}
      {!history.length && <div className="panel empty large">Nenhuma execução encontrada.</div>}
    </div>

    {pagination.pages > 1 && <div className="pagination-bar">
      <button disabled={page <= 1} onClick={() => setPage(page - 1)}>Anterior</button>
      <span>Página {pagination.page} de {pagination.pages} · {pagination.total} registros</span>
      <button disabled={page >= pagination.pages} onClick={() => setPage(page + 1)}>Próxima</button>
    </div>}
  </div>;
}
