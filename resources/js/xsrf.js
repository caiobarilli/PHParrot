export const xsrf = (cookies = document.cookie) => {
    const cookie = cookies.split('; ').find((linha) => linha.startsWith('XSRF-TOKEN='));

    return cookie === undefined ? '' : decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
};
