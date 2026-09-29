export type ContextQualityLevel = 'empty' | 'very-basic' | 'basic' | 'good' | 'excellent';

export type ContextQuality = {
  score: number;
  level: ContextQualityLevel;
  label: string;
  hint: string;
  acceptable: boolean;
  missing: string[];
};

const ACCEPTABLE_SCORE = 82;

const levelMeta: Record<ContextQualityLevel, {label:string;hint:string}> = {
  empty: {label: 'Sem contexto', hint: 'Adicione informações reais para orientar os agentes.'},
  'very-basic': {label: 'Muito básico', hint: 'Ainda faltam detalhes essenciais para uma resposta confiável.'},
  basic: {label: 'Em construção', hint: 'Já existe contexto, mas ele ainda pode gerar respostas genéricas.'},
  good: {label: 'Pronto para uso', hint: 'Contexto suficientemente específico para orientar os agentes.'},
  excellent: {label: 'Excelente', hint: 'Contexto claro, específico e com ótimo nível de detalhe.'},
};

const broadSegments = /^(sa[uú]de|servi[cç]os?|com[eé]rcio|tecnologia|educa[cç][aã]o|beleza|marketing|consultoria|neg[oó]cios?|empresa)$/i;
const splitItems = (text:string) => text.split(/\n|;|•|\|/).map(v=>v.trim()).filter(Boolean);
const countClauses = (text:string) => text.split(/[.;\n]|\s+-\s+/).map(v=>v.trim()).filter(v=>v.length>4).length;
const has = (text:string,re:RegExp)=>re.test(text);

function genericScore(text:string,targetLength:number){
  const words=text.toLocaleLowerCase('pt-BR').match(/[\p{L}\p{N}]+/gu)??[];
  const unique=new Set(words.filter(w=>w.length>2));
  let score=Math.min(62,Math.round((text.length/Math.max(60,targetLength))*62));
  if(words.length>=8)score+=8;
  if(words.length>=16)score+=6;
  if(unique.size>=10)score+=7;
  if(/[,;:\n]/.test(text))score+=5;
  if(/\d|R\$|%/.test(text))score+=4;
  if(countClauses(text)>=2)score+=6;
  return Math.max(8,Math.min(100,score));
}

function fieldScore(field:string,text:string,targetLength:number):{score:number;missing:string[]}{
  const missing:string[]=[];
  const words=text.match(/[\p{L}\p{N}]+/gu)??[];
  const items=splitItems(text);
  let score=genericScore(text,targetLength);

  switch(field){
    case 'segment': {
      const clean=text.replace(/[.,;:]+$/,'').trim();
      score=22;
      if(words.length>=2)score+=28; else missing.push('especificar a especialidade ou nicho');
      if(words.length>=3&&words.length<=9)score+=18;
      if(!broadSegments.test(clean))score+=24; else missing.push('trocar o segmento genérico por um nicho mais específico');
      if(/\b(est[eé]tica|odontol[oó]gica|cl[ií]nica|software|cont[aá]bil|jur[ií]dica|industrial|varejo|b2b|b2c|especializada|especializado)\b/i.test(text))score+=8;
      break;
    }
    case 'description': {
      score=Math.min(40,Math.round(text.length/4));
      const what=has(text,/\b(oferece|atua|especializ|desenvolve|vende|presta|atende|realiza|produz|fornece|empresa|cl[ií]nica|ag[eê]ncia|escrit[oó]rio)\b/i);
      const who=has(text,/\b(clientes?|pacientes?|empresas?|adultos?|fam[ií]lias?|profissionais?|p[uú]blico|neg[oó]cios?|consumidores?)\b/i);
      const how=has(text,/\b(com foco|atrav[eé]s|por meio|atendimento|acompanhamento|processo|metodologia|especializada|especializado|personalizado|digital|presencial|online)\b/i);
      if(what)score+=18;else missing.push('explicar o que a empresa faz');
      if(who)score+=18;else missing.push('indicar para quem a empresa atende');
      if(how)score+=18;else missing.push('explicar como trabalha ou qual é o foco');
      if(countClauses(text)>=2)score+=8;
      break;
    }
    case 'business_hours': {
      score=12;
      const day=has(text,/\b(segunda|ter[cç]a|quarta|quinta|sexta|s[aá]bado|domingo|seg\.?|ter\.?|qua\.?|qui\.?|sex\.?)\b/i);
      const time=has(text,/\b\d{1,2}([:h]\d{0,2})?\s*(?:h|horas?)?\b/i);
      const exception=has(text,/\b(feriado|agendamento|exceto|plant[aã]o|intervalo|almo[cç]o|fechado|somente)\b/i);
      if(day)score+=35;else missing.push('informar os dias de atendimento');
      if(time)score+=38;else missing.push('informar os horários');
      if(exception)score+=15;
      break;
    }
    case 'products': {
      score=Math.min(45,Math.round(text.length/4));
      const qty=items.length>=2||countClauses(text)>=2;
      if(qty)score+=30;else missing.push('listar pelo menos duas soluções, linhas ou categorias quando existirem');
      if(words.length>=12)score+=18;else missing.push('dar uma breve descrição do que é oferecido');
      if(/\b(para|inclui|indicado|voltado|solu[cç][aã]o|servi[cç]o|produto)\b/i.test(text))score+=7;
      break;
    }
    case 'target_audience': {
      score=Math.min(34,Math.round(text.length/5));
      const profile=has(text,/\b(adultos?|jovens?|fam[ií]lias?|empresas?|neg[oó]cios?|profissionais?|gestores?|pacientes?|clientes?|homens?|mulheres?|b2b|b2c|pequenas|m[eé]dias|grandes)\b/i);
      const need=has(text,/\b(buscam|precisam|querem|necessitam|interessad|objetivo|dor|desafio|melhorar|resolver|contratar|comprar)\b/i);
      const qualifier=has(text,/\b(anos?|regi[aã]o|cidade|estado|brasil|r\$|renda|setor|segmento|momento|particular|online|presencial)\b/i);
      if(profile)score+=24;else missing.push('definir o perfil principal do público');
      if(need)score+=24;else missing.push('explicar a principal necessidade ou intenção desse público');
      if(qualifier)score+=18;else missing.push('adicionar um recorte útil, como região, perfil ou momento de compra');
      break;
    }
    case 'differentials': {
      score=Math.min(38,Math.round(text.length/5));
      const concrete=has(text,/\b(atendimento|prazo|tecnologia|equipamento|acompanhamento|suporte|certifica[cç][aã]o|experi[eê]ncia|garantia|processo|metodologia|entrega|equipe|especialista|hora marcada|personalizado|digital)\b/i);
      const clauses=countClauses(text)>=2||items.length>=2;
      if(concrete)score+=30;else missing.push('trocar elogios genéricos por diferenciais concretos');
      if(clauses)score+=24;else missing.push('informar pelo menos dois diferenciais quando existirem');
      if(countClauses(text)>=3||items.length>=3)score+=8;
      if(/\b(melhor|n[uú]mero 1|incompar[aá]vel|perfeito|garantido)\b/i.test(text))score-=12;
      break;
    }
    case 'avoid_words':
    case 'preferred_words': {
      score=24;
      const qty=items.length>=2||countClauses(text)>=2||/[,;]/.test(text);
      if(qty)score+=52;else missing.push('adicionar mais de um exemplo ou regra');
      if(text.length>=35)score+=18;
      if(/\b(n[aã]o|evitar|usar|preferir|chamar|dizer|termo|express[aã]o)\b/i.test(text))score+=6;
      break;
    }
    case 'specific_rules': {
      score=Math.min(34,Math.round(text.length/5));
      const directive=has(text,/\b(n[aã]o|nunca|sempre|deve|evitar|quando|somente|jamais|priorizar|orientar)\b/i);
      const qty=countClauses(text)>=2||items.length>=2;
      if(directive)score+=30;else missing.push('escrever regras de forma objetiva: “sempre…”, “nunca…”, “quando…”');
      if(qty)score+=28;else missing.push('adicionar pelo menos duas regras quando necessário');
      break;
    }
    case 'additional_info': {
      score=genericScore(text,Math.min(targetLength,120));
      if(text.length<45)missing.push('explicar por que essa informação é relevante para o atendimento');
      break;
    }
    default: break;
  }

  return {score:Math.max(8,Math.min(100,Math.round(score))),missing:missing.slice(0,3)};
}

export function evaluateContext(value:unknown,targetLength=140,field='generic'):ContextQuality{
  const text=String(value??'').trim().replace(/[ \t]+/g,' ');
  if(!text)return {score:0,level:'empty',acceptable:false,missing:['preencher o campo'],...levelMeta.empty};
  const {score,missing}=fieldScore(field,text,targetLength);
  const level:ContextQualityLevel=score<30?'very-basic':score<ACCEPTABLE_SCORE?'basic':score<92?'good':'excellent';
  return {score,level,acceptable:score>=ACCEPTABLE_SCORE,missing,...levelMeta[level]};
}

export function aggregateContextQuality(values:Array<{value:unknown;targetLength?:number;weight?:number;field?:string;optional?:boolean}>):ContextQuality{
  let weighted=0,totalWeight=0;
  const missing:string[]=[];
  for(const item of values){
    const text=String(item.value??'').trim();
    if(item.optional&&!text)continue;
    const weight=item.weight??1;
    const result=evaluateContext(item.value,item.targetLength??140,item.field??'generic');
    weighted+=result.score*weight;
    totalWeight+=weight;
    if(!result.acceptable&&result.missing.length)missing.push(...result.missing);
  }
  if(!totalWeight)return {score:0,level:'empty',acceptable:false,missing:['preencher os campos essenciais'],...levelMeta.empty};
  const score=Math.round(weighted/totalWeight);
  const level:ContextQualityLevel=score===0?'empty':score<30?'very-basic':score<ACCEPTABLE_SCORE?'basic':score<92?'good':'excellent';
  return {score,level,acceptable:score>=ACCEPTABLE_SCORE,missing:[...new Set(missing)].slice(0,3),...levelMeta[level]};
}

export const CONTEXT_ACCEPTABLE_SCORE=ACCEPTABLE_SCORE;
