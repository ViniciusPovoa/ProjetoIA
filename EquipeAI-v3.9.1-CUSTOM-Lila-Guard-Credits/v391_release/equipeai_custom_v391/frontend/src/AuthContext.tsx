import {createContext,useContext,useEffect,useState,ReactNode} from 'react';
import {api} from './api';

export type User={id:number;name:string;email:string;role:'user'|'admin';company_id:number;status?:string;email_verified_at?:string|null};
export type RegistrationResult={message:string;email:string;email_sent:boolean;verification_required:boolean};
type Ctx={user:User|null;loading:boolean;login:(e:string,p:string)=>Promise<void>;register:(n:string,e:string,p:string,c:string,terms:boolean)=>Promise<RegistrationResult>;logout:()=>Promise<void>;refresh:()=>Promise<void>;resendVerification:(e:string)=>Promise<string>};
const AuthContext=createContext<Ctx>({} as Ctx);
export const useAuth=()=>useContext(AuthContext);

export function AuthProvider({children}:{children:ReactNode}){
  const[user,setUser]=useState<User|null>(null);
  const[loading,setLoading]=useState(true);

  const clearSession=()=>{sessionStorage.removeItem('access_token');sessionStorage.removeItem('csrf_token')};
  const hydrate=async()=>{const token=sessionStorage.getItem('access_token');if(!token){setUser(null);setLoading(false);return}try{const r=await api.get('/me');setUser(r.data.user)}catch{clearSession();setUser(null)}finally{setLoading(false)}};
  useEffect(()=>{hydrate()},[]);

  const login=async(email:string,password:string)=>{const r=await api.post('/auth/login',{email,password});sessionStorage.setItem('access_token',r.data.token);sessionStorage.setItem('csrf_token',r.data.csrf_token);setUser(r.data.user)};
  const register=async(name:string,email:string,password:string,company_name:string,terms_accepted:boolean)=>{const r=await api.post('/auth/register',{name,email,password,company_name,terms_accepted});return r.data as RegistrationResult};
  const resendVerification=async(email:string)=>{const r=await api.post('/auth/resend-verification',{email});return r.data.message as string};
  const logout=async()=>{try{await api.post('/auth/logout')}finally{clearSession();setUser(null)}};

  return <AuthContext.Provider value={{user,loading,login,register,logout,refresh:hydrate,resendVerification}}>{children}</AuthContext.Provider>;
}
