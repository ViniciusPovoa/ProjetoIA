type EmbeddedSignupSdk={app_id:string;config_id:string;graph_version:string;feature_type?:string;embedded_signup_version?:string};
type EmbeddedSignupResult={code:string;waba_id?:string;phone_number_id?:string;business_id?:string;event?:string};

declare global {
  interface Window {
    FB?: {
      init:(options:Record<string,unknown>)=>void;
      login:(callback:(response:any)=>void,options:Record<string,unknown>)=>void;
    };
    fbAsyncInit?:()=>void;
  }
}

let sdkPromise:Promise<void>|null=null;

export function loadMetaSdk(sdk:EmbeddedSignupSdk):Promise<void>{
  if(window.FB){window.FB.init({appId:sdk.app_id,cookie:true,xfbml:false,version:sdk.graph_version||'v26.0'});return Promise.resolve();}
  if(sdkPromise)return sdkPromise;
  sdkPromise=new Promise((resolve,reject)=>{
    const existing=document.getElementById('facebook-jssdk');
    window.fbAsyncInit=()=>{window.FB?.init({appId:sdk.app_id,cookie:true,xfbml:false,version:sdk.graph_version||'v26.0'});resolve();};
    if(existing)return;
    const script=document.createElement('script');script.id='facebook-jssdk';script.async=true;script.defer=true;script.crossOrigin='anonymous';script.src='https://connect.facebook.net/pt_BR/sdk.js';script.onerror=()=>reject(new Error('Não foi possível carregar o login da Meta.'));document.body.appendChild(script);
  });
  return sdkPromise;
}

export async function launchWhatsAppEmbeddedSignup(sdk:EmbeddedSignupSdk):Promise<EmbeddedSignupResult>{
  if(!sdk.app_id||!sdk.config_id)throw new Error('Embedded Signup não configurado.');
  await loadMetaSdk(sdk);
  if(!window.FB)throw new Error('SDK da Meta indisponível.');

  return new Promise((resolve,reject)=>{
    let code='';
    let session:Partial<EmbeddedSignupResult>={};
    let finished=false;
    let fallbackTimer:number|undefined;
    const cleanup=()=>{window.removeEventListener('message',sessionListener);if(fallbackTimer)window.clearTimeout(fallbackTimer);};
    const finish=()=>{if(finished||!code)return;finished=true;cleanup();resolve({code,...session});};
    const sessionListener=(event:MessageEvent)=>{
      if(event.origin!=='https://www.facebook.com'&&event.origin!=='https://web.facebook.com')return;
      let data:any=event.data;
      try{if(typeof data==='string')data=JSON.parse(data);}catch{return;}
      if(!data||data.type!=='WA_EMBEDDED_SIGNUP')return;
      if(String(data.event||'').startsWith('FINISH')){
        session={
          event:String(data.event||''),
          waba_id:String(data.data?.waba_id||''),
          phone_number_id:String(data.data?.phone_number_id||''),
          business_id:String(data.data?.business_id||''),
        };
        if(code)finish();
      }
    };
    window.addEventListener('message',sessionListener);

    const extras:Record<string,unknown>={setup:{},version:sdk.embedded_signup_version||'v4',sessionInfoVersion:'3'};
    if(sdk.feature_type)extras.featureType=sdk.feature_type;
    window.FB!.login((response:any)=>{
      code=String(response?.authResponse?.code||'');
      if(!code){cleanup();reject(new Error('Conexão com o WhatsApp cancelada ou não autorizada.'));return;}
      if(session.waba_id){finish();return;}
      fallbackTimer=window.setTimeout(finish,1800);
    },{
      config_id:sdk.config_id,
      auth_type:'rerequest',
      response_type:'code',
      override_default_response_type:true,
      extras,
    });
  });
}
