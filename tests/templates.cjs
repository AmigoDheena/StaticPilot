// Real Apache rewrite tests using isolated content and generated client snippets.
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const {spawn, execFileSync} = require('node:child_process');
const php = process.env.PHP_BIN || 'C:/xampp/php/php.exe';
const apache = process.env.APACHE_BIN || 'C:/xampp/apache/bin/httpd.exe';
const apacheRoot = path.resolve(path.dirname(apache), '..').replaceAll('\\', '/');
const root = path.resolve(__dirname, '..');
const sandbox = fs.mkdtempSync(path.join(os.tmpdir(), 'staticpilot-templates-'));
const web = path.join(sandbox, 'www');
const run = (cwd, code) => execFileSync(php, ['-r', code], {cwd, encoding:'utf8'});
const apps = [];
for (const prefix of ['', 'client']) {
  const site = path.join(web, prefix), app = path.join(site, 'StaticPilot');
  fs.mkdirSync(path.join(app, 'storage'), {recursive:true});
  for (const file of fs.readdirSync(root)) {
    if (file.endsWith('.php') && file !== 'setup.php') fs.copyFileSync(path.join(root,file),path.join(app,file));
  }
  fs.writeFileSync(path.join(app,'setup.php'), "<?php return ['storage_path'=>__DIR__.'/storage'];");
  const data = JSON.parse(run(app, 'require "content-store.php"; echo json_encode(content_defaults());'));
  for (const collection of ['services', 'products']) {
    data[collection] = Array.from({length:5}, (_,i)=>({name:collection+' '+i, slug:'item-'+(i+(collection === 'products' ? 5 : 0)), description:'Text <script>unsafe</script>', seo_title:i?'':'SEO <title>', seo_description:'SEO "description"',custom_fields:{Price:'<100>'}}));
    const file = collection === 'services' ? 'service-template.php' : 'product-template.php';
    fs.writeFileSync(path.join(site,file),run(app, 'require "developer-help.php"; echo developer_detail_example("'+collection+'", ["Price"]);'));
  }
  Object.assign(data.services[0],{seo_keywords:'speed, <fast>',seo_robots:'index, follow',canonical_url:'/item-0',og_type:'website',og_url:'/item-0',og_title:'OG <title>',og_description:'OG "description"',og_image:'images/banner/3.jpg',twitter_card:'summary_large_image',twitter_url:'/item-0',twitter_title:'Twitter <title>',twitter_description:'Twitter "description"',twitter_image:'images/banner/3.jpg'});
  fs.writeFileSync(path.join(app,'storage/content.json'),JSON.stringify(data));
  fs.writeFileSync(path.join(site,'.htaccess'),run(app,'require "developer-help.php"; echo developer_template_rules();'));
  fs.writeFileSync(path.join(site,'content-router.php'),run(app,'require "developer-help.php"; echo developer_router_example();'));
  fs.writeFileSync(path.join(site,'index.php'),'<?php echo "Existing home";');
  fs.mkdirSync(path.join(site,'services'));
  fs.writeFileSync(path.join(site,'services','existing.txt'),'Existing file');
  apps.push(app);
}
let server;
(async()=>{
  try {
    const listener=net.createServer(); await new Promise(resolve=>listener.listen(0,'127.0.0.1',resolve));
    const port=listener.address().port; await new Promise(resolve=>listener.close(resolve));
    const slash = value=>value.replaceAll('\\','/');
    const conf = `ServerRoot "${apacheRoot}"
Listen 127.0.0.1:${port}
ServerName localhost
PidFile "${slash(path.join(sandbox,'httpd.pid'))}"
ErrorLog "${slash(path.join(sandbox,'error.log'))}"
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule dir_module modules/mod_dir.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadFile "${slash(path.join(path.dirname(php),'php8ts.dll'))}"
LoadModule php_module "${slash(path.join(path.dirname(php),'php8apache2_4.dll'))}"
DocumentRoot "${slash(web)}"
<Directory "${slash(web)}">
  Require all granted
  AllowOverride All
  Options FollowSymLinks
  DirectoryIndex index.php
</Directory>
<FilesMatch "\\.php$">
  SetHandler application/x-httpd-php
</FilesMatch>
`;
    const config=slash(path.join(sandbox,'httpd.conf')); fs.writeFileSync(config,conf);
    execFileSync(apache,['-t','-f',config],{stdio:'pipe'});
    server=spawn(apache,['-X','-f',config],{windowsHide:true,stdio:'ignore'});
    const base=`http://127.0.0.1:${port}`;
    for(let i=0;i<60;i++){try{await fetch(base);break;}catch{await new Promise(resolve=>setTimeout(resolve,100));}}
    async function request(url,status=200) {
      const r=await fetch(base+url,{redirect:'manual'}), body=await r.text();
      assert.equal(r.status,status,url+' '+body); assert.doesNotMatch(body, /Warning:|Fatal error:|Parse error:/);
      return body;
    }
    for (const prefix of ['', '/client']) {
      assert.match(await request(prefix+'/'),/Existing home/);
      assert.equal(await request(prefix+'/services/existing.txt'),'Existing file');
      for (const collection of ['services','products']) {
        for(let i=0;i<5;i++) {
          const body=await request(`${prefix}/item-${collection === 'services' ? i : i+5}`);
          assert.match(body,new RegExp(`<h1>${collection} ${i}</h1>`));
          assert.match(body,/&lt;script&gt;unsafe/); assert.match(body,/&lt;100&gt;/);
          assert.match(body,i ? new RegExp(`<title>${collection} ${i}</title>`) : /<title>SEO &lt;title&gt;<\/title>/);
          if (collection === 'services' && i === 0) { assert.match(body,/property="og:title" content="OG &lt;title&gt;"/);assert.match(body,/property="twitter:card" content="summary_large_image"/);assert.match(body,/rel="canonical" href="\/item-0"/); }
        }
        await request(`${prefix}/item-${collection === 'services' ? 0 : 5}/?campaign=test`);
      }
      for (const slug of ['UPPER','bad_slug','item-0/extra']) await request(`${prefix}/${slug}`,404);
      await request(prefix+'/missing',404);
      for(const file of ['service-template.php','product-template.php']) await request(prefix+'/'+file,404);
      const app=apps[prefix?1:0], content=path.join(app,'storage/content.json');
      const data=JSON.parse(fs.readFileSync(content)); data.services[0].name='Updated immediately';
      data.products.pop(); fs.writeFileSync(content,JSON.stringify(data));
      assert.match(await request(prefix+'/item-0'),/Updated immediately/);
      await request(prefix+'/item-9',404);
    }
    // Aliased base URL must not accept another site's matching route.
    const app=apps[1];
    const result=run(app,'$_SERVER["DOCUMENT_ROOT"]="'+slash(web)+'"; $_SERVER["REQUEST_URI"]="/item-0"; require "route.php"; echo json_encode([$notFound, http_response_code()]);');
    assert.deepEqual(JSON.parse(result),[true,404]);
    console.log('PASS: generated templates, all 10 items, real Apache rewrites, subdirectories, escaping, SEO, custom fields, 404s, existing files, and live edits/deletes.');
  } finally {
    if (server && server.exitCode === null) { server.kill(); await new Promise(resolve=>server.once('exit',resolve)); }
    fs.rmSync(sandbox,{recursive:true,force:true});
  }
})().catch(error=>{console.error(error);process.exitCode=1;});
