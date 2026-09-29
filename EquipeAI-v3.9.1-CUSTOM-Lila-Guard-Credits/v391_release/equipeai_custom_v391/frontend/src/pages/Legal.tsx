import {Link,useLocation} from 'react-router-dom';
import {FiArrowLeft} from 'react-icons/fi';
import Brand from '../components/Brand';

const terms=[
  ['1. Uso da plataforma','A EquipeAI fornece ferramentas de apoio à criação, organização e análise de conteúdo empresarial. O usuário continua responsável por revisar resultados antes de utilizá-los em comunicações, decisões ou documentos.'],
  ['2. Conta e segurança','Você deve fornecer dados verdadeiros, manter sua senha em sigilo e comunicar acessos não autorizados. O acesso pode ser limitado quando houver indícios de abuso, fraude ou risco de segurança.'],
  ['3. Créditos e planos','Os recursos consumidos são medidos por créditos internos. Preços, franquias e limites de cada plano são apresentados antes da contratação e podem ser atualizados para ciclos futuros.'],
  ['4. Conteúdo e uso aceitável','Não utilize a plataforma para atividades ilegais, fraude, invasão de privacidade, distribuição de malware ou conteúdo que viole direitos de terceiros.'],
  ['5. Disponibilidade','Buscamos manter o serviço disponível e seguro, mas integrações externas e provedores podem apresentar indisponibilidades temporárias.']
];
const privacy=[
  ['1. Dados que tratamos','Podemos tratar dados de cadastro, informações da empresa, histórico de uso, arquivos enviados, informações técnicas de sessão e dados necessários à cobrança.'],
  ['2. Finalidades','Os dados são utilizados para autenticação, execução das funcionalidades, personalização do contexto da empresa, segurança, suporte, cobrança e melhoria operacional do serviço.'],
  ['3. Provedores de tecnologia','Conteúdo enviado aos módulos pode ser encaminhado ao provedor de IA configurado para executar a tarefa. Integrações de pagamento e e-mail recebem somente os dados necessários à respectiva finalidade.'],
  ['4. Segurança e retenção','Aplicamos controles de autenticação, autorização, logs, limitação de requisições e isolamento por empresa. Os períodos de retenção podem variar conforme o tipo de dado e obrigações aplicáveis.'],
  ['5. Seus direitos','Você pode solicitar correção ou exclusão de dados quando aplicável. A própria plataforma também disponibiliza exclusão de conta, histórico e arquivos.']
];
export default function Legal(){const{pathname}=useLocation();const isPrivacy=pathname.includes('privacidade');const sections=isPrivacy?privacy:terms;return <div className="legal-page"><header><Brand/><Link to="/cadastro"><FiArrowLeft/> Voltar ao cadastro</Link></header><main><span className="auth-step">VERSÃO 2026-09</span><h1>{isPrivacy?'Política de Privacidade':'Termos de Uso'}</h1><p className="legal-lead">Documento operacional do MVP da EquipeAI. Antes da comercialização em escala, recomendamos revisão jurídica de acordo com a sua empresa, modelo de negócio e obrigações aplicáveis.</p>{sections.map(([title,text])=><section key={title}><h2>{title}</h2><p>{text}</p></section>)}<section><h2>Contato</h2><p>Dúvidas sobre conta, segurança ou privacidade podem ser encaminhadas para o canal de suporte informado na plataforma.</p></section></main></div>}
