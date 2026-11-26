import Pusher from 'pusher-js';
import AuthController from '@/actions/App/Http/Controllers/AuthController';

window.Pusher = Pusher;

const token = localStorage.getItem('tma_token');

const reverbHost = import.meta.env.VITE_REVERB_HOST;
const reverbPort = import.meta.env.VITE_REVERB_PORT;
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME;
const appKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbCluster = import.meta.env.VITE_REVERB_APP_CLUSTER || 'mt1';

window.Echo = {
  private(channel) {
    const normalized = channel.startsWith('private-') ? channel.slice(8) : channel;
    return {
      listen(event, callback) {
        window.addEventListener('reverb:' + normalized + ':' + event, (e) => callback(e.detail));
        return this;
      },
      error(callback) {
        return this;
      },
    };
  },
  presence(channel) {
    return {
      here(callback) {
        callback([]);
        return this;
      },
      joining(callback) {
        return this;
      },
      leaving(callback) {
        return this;
      },
      error(callback) {
        return this;
      },
    };
  },
  leave(channel) {},
  disconnect() {},
};

if (reverbHost && reverbPort && appKey) {
  try {
    const pusher = new Pusher(appKey, {
      cluster: reverbCluster,
      wsHost: reverbHost,
      wsPort: Number(reverbPort),
      wssPort: Number(reverbPort),
      forceTLS: reverbScheme === 'https',
      enabledTransports: ['ws', 'wss'],
      channelAuthorization: {
        transport: 'ajax',
        endpoint: '/broadcasting/auth',
        customHandler: async ({ channelName, socketId }, callback) => {
          const fetchAuth = async (authToken) => {
            const headers = {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            };
            if (authToken) {
              headers['Authorization'] = 'Bearer ' + authToken;
              headers['X-Session-Token'] = authToken;
            }
            return fetch('/broadcasting/auth', {
              method: 'POST',
              credentials: 'include',
              headers,
              body: JSON.stringify({
                socket_id: socketId,
                channel_name: channelName,
              }),
            });
          };

          try {
            let token = window.__tmaToken || window.__tmaApp?.user?.session_token || localStorage.getItem('tma_token') || '';
            let response = await fetchAuth(token);

            if (response.status === 401) {
              if (window.__tmaApp?.authenticate) {
                await window.__tmaApp.authenticate();
              } else if (window.Telegram?.WebApp?.initData) {
                try {
                  const authRes = await fetch(AuthController.tma.url(), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ initData: window.Telegram.WebApp.initData }),
                  });
                  const authData = await authRes.json();
                  if (authData?.session_token) {
                    localStorage.setItem('tma_token', authData.session_token);
                    window.__tmaToken = authData.session_token;
                  }
                } catch (_) {}
              }
              token = window.__tmaToken || window.__tmaApp?.user?.session_token || localStorage.getItem('tma_token') || '';
              response = await fetchAuth(token);
            }

            if (!response.ok) {
              throw new Error('Auth failed: ' + response.status);
            }

            const data = await response.json();
            callback(null, data);
          } catch (err) {
            console.error('[Echo] Auth error for ' + channelName, err);
            callback(err);
          }
        },
      },
    });

    window.Echo = {
      private(channel) {
        const channelName = channel.startsWith('private-') ? channel : `private-${channel}`;
        const pusherChannel = pusher.subscribe(channelName);
        return {
          listen(event, callback) {
            const normalizedEvent = event.startsWith('.') ? event.slice(1) : event;
            pusherChannel.bind(normalizedEvent, callback);
            return this;
          },
          error(callback) {
            pusherChannel.bind('pusher:subscription_error', callback);
            return this;
          },
        };
      },
      presence(channel) {
        const channelName = channel.startsWith('presence-') ? channel : `presence-${channel}`;
        const pusherChannel = pusher.subscribe(channelName);
        const self = {
          here(callback) {
            const list = [];
            pusherChannel.bind('pusher:subscription_succeeded', (members) => {
              if (members && typeof members.each === 'function') {
                members.each((member) => list.push(member));
              }
              callback(list);
            });
            return self;
          },
          joining(callback) {
            pusherChannel.bind('pusher:member_added', (member) => callback(member));
            return self;
          },
          leaving(callback) {
            pusherChannel.bind('pusher:member_removed', (member) => callback(member));
            return self;
          },
          error(callback) {
            pusherChannel.bind('pusher:subscription_error', callback);
            return self;
          },
        };
        return self;
      },
      leave(channel) {
        try {
          const channelName = channel.startsWith('private-') || channel.startsWith('presence-') ? channel : `private-${channel}`;
          pusher.unsubscribe(channelName);
        } catch (_) {}
      },
      disconnect() {
        try {
          pusher.disconnect();
        } catch (_) {}
      },
    };
  } catch (e) {
    console.error('Echo init failed:', e);
  }
}

window.dispatchEvent(new CustomEvent('echo-ready'));

