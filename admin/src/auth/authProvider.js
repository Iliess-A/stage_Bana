import { fetchUtils } from 'react-admin';
import decodeJwt from 'jwt-decode';

import { getPermissions } from './permissions';

require('dotenv').config();

const authenticationTokenUri = process.env.REACT_APP_API_LOGIN;

const clearAuthStorage = () => {
  global.localStorage.removeItem('token');
  global.localStorage.removeItem('roles');
  global.localStorage.removeItem('id');
  global.localStorage.removeItem('permission');
};

export default {
  login: ({ username, password }) => {
    const request = new global.Request(authenticationTokenUri, {
      method: 'POST',
      body: JSON.stringify({ username, password }),
      headers: new global.Headers({ 'Content-Type': 'application/json' }),
    });

    return global
      .fetch(request)
      .then((response) => {
        if (response.status < 200 || response.status >= 300) throw new Error(response.statusText);
        return response.json();
      })
      .then(({ token }) => {
        const decodedToken = decodeJwt(token);
        if (!decodedToken.admin) throw new Error('Unauthorized');

        global.localStorage.setItem('token', token);
        global.localStorage.setItem('roles', JSON.stringify(Object.values(decodedToken.roles)));
        global.localStorage.setItem('id', decodedToken.id);

        const options = { headers: new global.Headers({ Accept: 'application/json' }) };
        options.headers.set('Authorization', `Bearer ${global.localStorage.token}`);

        return fetchUtils
          .fetchJson(`${process.env.REACT_APP_API}/permissions`, {
            method: 'GET',
            headers: options.headers,
          })
          .then((result) => {
            if (result.status === 200) {
              return global.localStorage.setItem(
                'permission',
                JSON.stringify(Object.values(result.json))
              );
            }

            return result;
          });
      });
  },
  logout: () => {
    clearAuthStorage();
    return Promise.resolve();
  },
  checkAuth: () => {
    if (global.localStorage.getItem('token')) {
      return Promise.resolve();
    }

    return Promise.reject({ redirectTo: '/login' });
  },
  getPermissions: () => {
    return Promise.resolve(getPermissions());
  },
  checkError: ({ status }) => {
    if (status === 401 || status === 403) {
      clearAuthStorage();
      return Promise.reject();
    }
    return Promise.resolve();
  },
};