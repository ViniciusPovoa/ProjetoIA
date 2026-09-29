import axios from 'axios';

const API_URL = (import.meta.env.VITE_API_URL || '/api').replace(/\/$/, '');

export const api = axios.create({
  baseURL: API_URL,
  timeout: 90000,
  withCredentials: false,
});

api.interceptors.request.use((config) => {
  const token = sessionStorage.getItem('access_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;

  const csrf = sessionStorage.getItem('csrf_token');
  if (csrf && config.method && !['get', 'head', 'options'].includes(config.method.toLowerCase())) {
    config.headers['X-CSRF-Token'] = csrf;
  }
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && location.pathname.startsWith('/app')) {
      sessionStorage.removeItem('access_token');
      sessionStorage.removeItem('csrf_token');
      location.href = '/login';
    }
    return Promise.reject(error);
  },
);
