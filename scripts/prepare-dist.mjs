import { cp, mkdir, rm, writeFile } from 'node:fs/promises'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = dirname(dirname(fileURLToPath(import.meta.url)))
const dist = join(root, 'dist')

const storageHtaccess = `Options -Indexes

<FilesMatch "^(config|auth)\\.json$">
    Require all denied
</FilesMatch>

<FilesMatch "^\\.">
    Require all denied
</FilesMatch>

<FilesMatch "\\.(php|phtml|phar)$">
    Require all denied
</FilesMatch>
`

const denyAllHtaccess = `Require all denied
`

const photosHtaccess = `Options -Indexes

<FilesMatch "\\.(php|phtml|phar)$">
    Require all denied
</FilesMatch>
`

await rm(join(dist, 'api'), { recursive: true, force: true })
await rm(join(dist, 'storage'), { recursive: true, force: true })

await cp(join(root, 'api'), join(dist, 'api'), { recursive: true })
await cp(join(root, '.htaccess'), join(dist, '.htaccess'))
await cp(join(root, '.env.example'), join(dist, '.env.example'))

await mkdir(join(dist, 'storage', 'imports'), { recursive: true })
await mkdir(join(dist, 'storage', 'lists'), { recursive: true })
await mkdir(join(dist, 'storage', 'photos'), { recursive: true })
await mkdir(join(dist, 'storage', 'sessions'), { recursive: true })

await writeFile(join(dist, 'storage', '.htaccess'), storageHtaccess)
await writeFile(join(dist, 'storage', 'imports', '.htaccess'), denyAllHtaccess)
await writeFile(join(dist, 'storage', 'lists', '.htaccess'), denyAllHtaccess)
await writeFile(join(dist, 'storage', 'sessions', '.htaccess'), denyAllHtaccess)
await writeFile(join(dist, 'storage', 'photos', '.htaccess'), photosHtaccess)

console.log('dist voorbereid met api/, lege storage/, .htaccess en .env.example')
