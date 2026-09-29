import {Link} from 'react-router-dom';

type Props={to?:string;compact?:boolean;inverse?:boolean};
export default function Brand({to='/',compact=false,inverse=false}:Props){
  const content=<><span className="brand-mark" aria-hidden="true"><i/><i/></span><span className="brand-copy"><span className="brand-name">EquipeAI</span>{!compact&&<small>workspace operacional</small>}</span></>;
  return <Link to={to} className={`brand ${inverse?'brand-inverse':''}`}>{content}</Link>;
}
