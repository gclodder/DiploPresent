const apiPath = (endpoint) => `${import.meta.env.BASE_URL}api/${endpoint}`.replace('/./', '/')

async function request(endpoint, options = {}) {
  const response = await fetch(apiPath(endpoint), {
    ...options,
    headers: {
      Accept: 'application/json',
      ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
      ...options.headers,
    },
  })

  let payload
  try {
    payload = await response.json()
  } catch {
    throw new Error(`De server gaf geen geldige JSON terug (${response.status}).`)
  }

  if (!response.ok || payload.success === false) {
    throw new Error(payload.error?.message || `Request mislukt (${response.status}).`)
  }

  return payload.data
}

export const api = {
  getConfig: () => request('config.php'),
  updateConfig: (config) =>
    request('config.php', { method: 'PUT', body: JSON.stringify(config) }),
  getGroupPhotos: () => request('group-photo.php'),
  uploadGroupPhoto: (department, file) => {
    const body = new FormData()
    body.append('department', department)
    body.append('file', file)
    return request('group-photo.php', { method: 'POST', body })
  },
  deleteGroupPhoto: (department) =>
    request(`group-photo.php?department=${encodeURIComponent(department)}`, { method: 'DELETE' }),
  getFiles: (type) => request(`files.php?type=${encodeURIComponent(type)}`),
  getImport: (name) => request(`import.php?name=${encodeURIComponent(name)}`),
  getList: (name) => request(`lists.php?name=${encodeURIComponent(name)}`),
  saveList: (name, students) =>
    request('lists.php', {
      method: 'POST',
      body: JSON.stringify({ name, students }),
    }),
  uploadImport: (file) => {
    const body = new FormData()
    body.append('file', file)
    return request('upload.php', { method: 'POST', body })
  },
  createSession: (settings) =>
    request('session.php', { method: 'POST', body: JSON.stringify(settings) }),
  getSession: (id) => request(`session.php?id=${encodeURIComponent(id)}`),
  updateSession: (id, controllerToken, changes) =>
    request(`session.php?id=${encodeURIComponent(id)}`, {
      method: 'PUT',
      body: JSON.stringify({ controllerToken, ...changes }),
    }),
}
