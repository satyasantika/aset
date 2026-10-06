import hmac, hashlib, struct, time, base64, json, os
from playwright.sync_api import sync_playwright
B='http://172.18.0.39:8123'; OUT='/tmp/panduan/img'; os.makedirs(OUT,exist_ok=True)
PROY='01a11198-7199-73be-ab3f-1a38581fdeda'
def totp(secret):
    k=base64.b32decode(secret); c=struct.pack('>Q',int(time.time())//30)
    h=hmac.new(k,c,hashlib.sha1).digest(); o=h[-1]&15
    return '%06d'%((struct.unpack('>I',h[o:o+4])[0]&0x7fffffff)%1000000)
done=[]; failed=[]
def shot(pg,role,name):
    pg.wait_for_timeout(1200)
    pg.screenshot(path=f'{OUT}/{role}-{name}.jpg',type='jpeg',quality=72); done.append(f'{role}-{name}')
def login(b,email,mfa=False):
    ctx=b.new_context(viewport={'width':1280,'height':800}); pg=ctx.new_page()
    pg.goto(B+'/admin/login'); pg.fill('input[type=email]',email); pg.fill('input[type=password]','PanduanUji123'); pg.click('button[type=submit]')
    pg.wait_for_timeout(2500)
    if mfa:
        pg.click('input >> nth=0'); pg.keyboard.type(totp('JBSWY3DPEHPK3PXP')); pg.wait_for_timeout(300)
        pg.click('button:has-text("Konfirmasi login")'); pg.wait_for_timeout(2500)
    return ctx,pg
def go(pg,path,wait=1500):
    pg.goto(B+path); pg.wait_for_timeout(wait)
def first_row(pg):
    pg.click('table tbody tr >> nth=0 >> a >> nth=0'); pg.wait_for_timeout(1800)
def tryit(role,name,fn):
    try: fn()
    except Exception as e: failed.append((role,name,str(e)[:120]))
with sync_playwright() as p:
    b=p.chromium.launch()
    # publik
    ctx=b.new_context(viewport={'width':1280,'height':800}); pg=ctx.new_page()
    pg.goto(B+'/admin/login'); shot(pg,'umum','login')
    pg.goto(B+'/a/'+PROY); shot(pg,'publik','lookup')
    pg.goto(B+'/lapor-kerusakan/'+PROY); shot(pg,'publik','lapor')
    pg.fill('textarea','Lensa proyektor berembun dan gambar buram'); shot(pg,'publik','lapor-isi')
    ctx.close()
    # civitas
    ctx,pg=login(b,'uat.dosen@unsil.ac.id')
    for n,pth in [('pinjam','/pinjam'),('pinjaman-saya','/pinjaman-saya')]:
        tryit('civitas',n,lambda: (go(pg,pth),shot(pg,'civitas',n)))
    tryit('civitas','pindai',lambda:(go(pg,'/pindai'),shot(pg,'civitas','pindai')))
    ctx.close()
    # pic lab
    ctx,pg=login(b,'uat.pic.aula@unsil.ac.id')
    shot(pg,'pic','dashboard')
    for n,pth in [('aset','/admin/aset'),('peminjaman','/admin/peminjaman'),('pemeliharaan','/admin/tiket-pemeliharaan'),('mutasi','/admin/mutasi'),('dbr','/admin/dbr'),('inventarisasi','/admin/inventarisasi'),('pindai','/pindai'),('keranjang','/keranjang')]:
        tryit('pic',n,lambda n=n,pth=pth:(go(pg,pth),shot(pg,'pic',n)))
    def kondisi():
        go(pg,'/admin/aset'); pg.click('table tbody tr >> nth=0 >> button:has-text("Ubah kondisi"), table tbody tr >> nth=0 >> a:has-text("Ubah kondisi")'); shot(pg,'pic','ubah-kondisi')
    tryit('pic','ubah-kondisi',kondisi)
    tryit('pic','dbr-detail',lambda:(go(pg,'/admin/dbr'),first_row(pg),shot(pg,'pic','dbr-detail')))
    tryit('pic','peminjaman-detail',lambda:(go(pg,'/admin/peminjaman'),first_row(pg),shot(pg,'pic','peminjaman-detail')))
    ctx.close()
    # admin
    ctx,pg=login(b,'uat.admin@unsil.ac.id',True)
    shot(pg,'admin','dashboard')
    for n,pth in [('aset','/admin/aset'),('aset-baru','/admin/aset/create'),('ruangan','/admin/ruangan'),('pengguna','/admin/pengguna'),('mutasi','/admin/mutasi'),('peminjaman','/admin/peminjaman'),('dbr','/admin/dbr'),('inventarisasi','/admin/inventarisasi'),('penghapusan','/admin/usulan-penghapusan'),('laporan','/admin/laporan'),('kodefikasi','/admin/kodefikasi-barang'),('label-ulang','/admin/aset/label-perlu-cetak-ulang'),('log','/admin/log-aktivitas')]:
        tryit('admin',n,lambda n=n,pth=pth:(go(pg,pth),shot(pg,'admin',n)))
    tryit('admin','mutasi-detail',lambda:(go(pg,'/admin/mutasi'),first_row(pg),shot(pg,'admin','mutasi-detail')))
    tryit('admin','inventarisasi-detail',lambda:(go(pg,'/admin/inventarisasi'),first_row(pg),shot(pg,'admin','inventarisasi-detail')))
    tryit('admin','penghapusan-detail',lambda:(go(pg,'/admin/usulan-penghapusan'),first_row(pg),shot(pg,'admin','penghapusan-detail')))
    ctx.close()
    # pejabat
    ctx,pg=login(b,'uat.pejabat@unsil.ac.id')
    shot(pg,'pejabat','dashboard')
    for n,pth in [('dbr','/admin/dbr'),('peminjaman','/admin/peminjaman'),('inventarisasi','/admin/inventarisasi'),('penghapusan','/admin/usulan-penghapusan'),('laporan','/admin/laporan')]:
        tryit('pejabat',n,lambda n=n,pth=pth:(go(pg,pth),shot(pg,'pejabat',n)))
    tryit('pejabat','dbr-detail',lambda:(go(pg,'/admin/dbr'),first_row(pg),shot(pg,'pejabat','dbr-detail')))
    tryit('pejabat','penghapusan-detail',lambda:(go(pg,'/admin/usulan-penghapusan'),first_row(pg),shot(pg,'pejabat','penghapusan-detail')))
    ctx.close()
    # pimpinan
    ctx,pg=login(b,'uat.pimpinan@unsil.ac.id')
    shot(pg,'pimpinan','dashboard')
    for n,pth in [('aset','/admin/aset'),('laporan','/admin/laporan')]:
        tryit('pimpinan',n,lambda n=n,pth=pth:(go(pg,pth),shot(pg,'pimpinan',n)))
    ctx.close()
    # super
    ctx,pg=login(b,'uat.super@unsil.ac.id',True)
    shot(pg,'super','dashboard')
    for n,pth in [('pengaturan','/admin/pengaturan-sistem'),('pengguna','/admin/pengguna'),('token','/admin/token-api/token-apis'),('log','/admin/log-aktivitas'),('laporan','/admin/laporan')]:
        tryit('super',n,lambda n=n,pth=pth:(go(pg,pth),shot(pg,'super',n)))
    ctx.close(); b.close()
print(len(done),'ok'); print(json.dumps(failed,indent=1))
