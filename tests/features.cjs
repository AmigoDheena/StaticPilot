// Isolated integration tests: never read or write the installation's content or credentials.
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const assert = require('node:assert/strict');
const {spawn, execFileSync} = require('node:child_process');
const php = process.env.PHP_BIN || 'C:/xampp/php/php.exe';
const root = path.resolve(__dirname, '..');
const sandbox = fs.mkdtempSync(path.join(os.tmpdir(), 'staticpilot-features-'));
const app = path.join(sandbox, 'StaticPilot');
fs.mkdirSync(app); fs.mkdirSync(path.join(app, 'storage'));
for (const file of fs.readdirSync(root)) {
  if (/\.(php|js|css)$/.test(file) && file !== 'setup.php') fs.copyFileSync(path.join(root,file),path.join(app,file));
}
fs.writeFileSync(path.join(app,'setup.php'), `<?php return ['username'=>'tester','password'=>'isolated-test-only','storage_path'=>__DIR__.'/storage','website_url'=>'../','per_page'=>8];`);
let server, base, cookie = '';
async function request(route, data) {
  const multipart = data instanceof FormData;
  const response = await fetch(base + route, {redirect:'manual',headers:{Cookie:cookie,...(data && !multipart ? {'Content-Type':'application/x-www-form-urlencoded'} : {})},...(data?{method:'POST',body:multipart?data:new URLSearchParams(data)}:{})});
  if (response.headers.get('set-cookie')) cookie = response.headers.get('set-cookie').split(';')[0];
  const body = await response.text();
  assert.doesNotMatch(body, /(?:<b>)?(?:Warning|Fatal error|Parse error):/);
  return {status:response.status,body,headers:response.headers};
}
const hidden = (html,key) => { const match=html.match(new RegExp('name="'+key+'" value="([^"]*)"')); assert(match,key); return match[1]; };
async function tokens(route) { const r=await request(route);return {csrf:hidden(r.body,'csrf'),revision:hidden(r.body,'revision')}; }
async function save(section, data, success=true) {
  const route='index.php?section='+section;
  const r=await request(route,{...await tokens(route),...data});
  if(success)assert.equal(r.status,302,r.body.slice(0,1000));
  return r;
}
const read = () => JSON.parse(fs.readFileSync(path.join(app,'storage/content.json'),'utf8'));
const defs = (fields, existing=false) => Object.assign({definitions_present:'1'}, ...fields.map((field,i)=>Object.fromEntries(Object.entries({...field,original_key:existing?field.key:''}).map(([k,v])=>[`field_defs[${i}][${k}]`,v]))));
async function upload(bytes, filename, extra={}) {
  const form=new FormData();
  for(const [k,v] of Object.entries({...await tokens('media.php'),action:'upload',title:'Sample image',alt:'A test image',...extra}))form.set(k,v);
  if(bytes)form.set('file',new Blob([bytes],{type:'image/png'}),filename);
  return request('media.php',form);
}
(async()=>{
  try {
    const listener=net.createServer();await new Promise(resolve=>listener.listen(0,'127.0.0.1',resolve));const port=listener.address().port;await new Promise(resolve=>listener.close(resolve));
    base=`http://127.0.0.1:${port}/StaticPilot/`;
    server=spawn(php,['-S',`127.0.0.1:${port}`,'-t',sandbox],{windowsHide:true,stdio:'ignore'});
    for(let i=0;i<50;i++){try{await fetch(base+'login.php');break;}catch{await new Promise(resolve=>setTimeout(resolve,100));}}
    assert.equal((await request('media.php')).status,302);
    let r=await request('login.php');await request('login.php',{csrf:hidden(r.body,'csrf'),username:'tester',password:'isolated-test-only'});
    const png=execFileSync(php,['-r','$i=imagecreatetruecolor(4,3); imagepng($i); imagedestroy($i);']);
    r=await upload(png,'sample.png');assert.equal(r.status,302);
    const imageId=Object.keys(read().media)[0];const oldFile=read().media[imageId].file;
    r=await request('media.php?format=json');const images=JSON.parse(r.body);assert.equal(images[0].id,imageId);
    const publicImage=await fetch(new URL(images[0].url,base));assert.equal(publicImage.status,200);assert.equal(publicImage.headers.get('content-type'),'image/png');
    assert.equal((await request('media-file.php?id=../../setup.php')).status,404);
    r=await upload(Buffer.from('<?php echo "bad"; ?>'),'fake.png');assert.match(r.body,/genuine JPEG/);
    r=await upload(png,'file.php');assert.match(r.body,/genuine JPEG/);
    r=await upload(Buffer.alloc(5*1024*1024+1),'large.png');assert.match(r.body,/5 MB/);
    r=await upload(png,'sample.png',{csrf:'bad'});assert.match(r.body,/session expired/);
    const giant=execFileSync(php,['-r','$i=imagecreatetruecolor(4000,3100); imagepng($i); imagedestroy($i);']);
    r=await upload(giant,'large-dimensions.png');assert.match(r.body,/12 megapixels/);
    const fields=[{key:'icon',label:'Icon',type:'text'},{key:'detail',label:'Detail',type:'textarea'},{key:'score',label:'Score',type:'number',required:'1'},{key:'link',label:'Link',type:'url'},{key:'photo',label:'Photo',type:'image'},{key:'featured',label:'Featured',type:'checkbox'},{key:'category',label:'Category',type:'select',options:'Quality\nSupport'}];
    await save('custom_sections',{action:'create',name:'Why choose us',slug:'why-choose-us',...defs(fields)});
    r=await request('index.php?section=custom:why-choose-us');for(const field of fields)assert.match(r.body,new RegExp('name="shared\\['+field.key+'\\]"'));
    const entry={action:'create',name:'Experience',description:'Years of care','shared[score]':'4.5','shared[link]':'https://example.com','shared[photo]':imageId,'shared[featured]':'1','shared[category]':'Quality','shared[icon]':'star','shared[detail]':'Long text'};
    r=await save('custom:why-choose-us',{...entry,'shared[score]':'no','shared[link]':'javascript:alert(1)','shared[photo]':'missing','shared[category]':'invalid'},false);
    for(const key of ['score','link','photo','category'])assert.match(r.body,new RegExp('id="error-shared-'+key+'"'));
    await save('custom:why-choose-us',entry);
    const renamed=fields.map(f=>f.key==='icon'?{...f,label:'Symbol'}:f);
    await save('custom_sections',{action:'save',id:'0',name:'Why choose us',slug:'why-choose-us',...defs(renamed,true)});
    assert.equal(read().custom_sections[0].items[0].custom_fields.icon,'star');
    r=await request('index.php?section=custom:why-choose-us&edit=0');assert.match(r.body,/Symbol/);assert.match(r.body,/value="star"/);assert.match(r.body,/content_image/);
    const changed=defs(renamed,true);changed['field_defs[0][key]']='changed';
    r=await save('custom_sections',{action:'save',id:'0',name:'Why choose us',slug:'why-choose-us',...changed},false);assert.match(r.body,/saved field key cannot be changed/);assert.match(r.body,/id="error-definition-0"/);
    changed['field_defs[0][key]']='icon';changed['field_defs[0][type]']='number';
    r=await save('custom_sections',{action:'save',id:'0',name:'Why choose us',slug:'why-choose-us',...changed},false);assert.match(r.body,/saved field type cannot be changed/);
    await save('custom:why-choose-us',{action:'duplicate',id:'0'});assert.equal(read().custom_sections[0].items.length,2);
    await save('custom_sections',{action:'duplicate',id:'0'});assert.equal(read().custom_sections[0].items.length,0);assert.equal(read().custom_sections[0].fields.length,7);assert.equal(read().custom_sections[0].slug,'why-choose-us-copy');
    await save('faqs',{action:'create',name:'Do you offer support?',description:'Yes, contact our team.'});
    await save('team',{action:'create',name:'Priya',position:'Engineer',description:'Builds websites',image_id:imageId});
    await save('team',{action:'create',name:'Arun',position:'Designer',description:'Designs websites'});
    r=await request('index.php?section=team&q=Engineer');assert.match(r.body,/>Priya<\/a>/);assert.doesNotMatch(r.body,/>Arun<\/a>/);
    r=await save('team',{action:'create',name:'',description:'',image_id:'invalid'},false);assert.match(r.body,/id="error-name"/);assert.match(r.body,/id="error-image_id"/);
    await save('products',{action:'create',name:'Lamp',slug:'lamp',description:'Desk lamp',image_id:imageId});await save('products',{action:'duplicate',id:'0'});assert.equal(read().products[0].slug,'lamp-copy');
    r=await request('index.php?section=products');assert.match(r.body,/data-url-pattern="\/products\/__SLUG__"/);assert.match(r.body,/id="view-mode-toggle"/);assert.match(r.body,/<details class="collection-nav"/);
    r=await upload(png,'replacement.png',{action:'replace',media_id:imageId,title:'Updated image',alt:'Updated alt text'});assert.equal(r.status,302);assert.notEqual(read().media[imageId].file,oldFile);assert.equal(fs.existsSync(path.join(app,'storage/media',oldFile)),false);assert.equal(read().products[0].image_id,imageId);
    const rendered=execFileSync(php,['-r','require "template-data.php"; echo content_image($products["lamp.php"]["image_id"]);'],{cwd:app,encoding:'utf8'});assert.match(rendered,/Updated alt text/);
    const definitions=read().custom_sections[1].fields;
    const output=execFileSync(php,['-r','require "template-data.php"; require "developer-help.php"; $defs=$custom_sections["why-choose-us"]["fields"]; $code=developer_section_example("custom:why-choose-us",array_column($defs,"key"),false,$defs); $code=str_replace("/StaticPilot/template-data.php","/template-data.php",$code); eval("?>".$code);'],{cwd:app,encoding:'utf8'});assert.match(output,/Updated alt text/);assert.match(output,/4.5/);assert.match(output,/Yes/);
    assert.equal(definitions.length,7);
    console.log('PASS: all seven shared field types, permanent keys, validation, media upload/replacement/security, FAQs/team, search, duplication, and template rendering (isolated storage).');
  } finally {
    if(server){const stopped=new Promise(resolve=>server.once('exit',resolve));server.kill();await stopped;}
    if(!path.resolve(sandbox).startsWith(path.join(os.tmpdir(),'staticpilot-features-')))throw Error('Unexpected test cleanup path');
    fs.rmSync(sandbox,{recursive:true,force:true});
  }
})().catch(error=>{console.error(error);process.exitCode=1;});
