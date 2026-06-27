(function(){
	// ===== 工具函数 =====
	var F = {};
	F.get = function(act, params, cb) {
		var url = 'index.php?act=' + act;
		if (params) for (var k in params) url += '&' + k + '=' + encodeURIComponent(params[k]);
		var xhr = new XMLHttpRequest();
		xhr.open('GET', url, true);
		xhr.onload = function() {
			try { cb(JSON.parse(xhr.responseText)); } catch(e) { cb({ok:false,err:'解析失败'}); }
		};
		xhr.onerror = function() { cb({ok:false,err:'网络错误'}); };
		xhr.send();
	};
	F.post = function(act, data, cb) {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', 'index.php?act=' + act, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.onload = function() {
			try { cb(JSON.parse(xhr.responseText)); } catch(e) { cb({ok:false,err:'解析失败'}); }
		};
		xhr.onerror = function() { cb({ok:false,err:'网络错误'}); };
		var s = '';
		for (var k in data) s += '&' + k + '=' + encodeURIComponent(data[k]);
		xhr.send(s.slice(1));
	};
	F.escHtml = function(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); };
	F.ficon = function(icon) {
		var m = {folder:'fa-folder', img:'fa-file-image', video:'fa-file-video', audio:'fa-file-audio', doc:'fa-file-pdf', zip:'fa-file-archive', code:'fa-file-code', cfg:'fa-file-code', exe:'fa-gears', file:'fa-file'};
		return m[icon] || m.file;
	};
	F.sizeTxt = function(s) {
		if (s >= 1073741824) return (s/1073741824).toFixed(2)+' GB';
		if (s >= 1048576) return (s/1048576).toFixed(2)+' MB';
		if (s >= 1024) return (s/1024).toFixed(1)+' KB';
		return s+' B';
	};
	F.toast = function(msg, type) {
		var el = document.querySelector('.toast');
		if (!el) {
			el = document.createElement('div');
			el.className = 'toast';
			document.body.appendChild(el);
		}
		el.textContent = msg;
		el.className = 'toast' + (type ? ' ' + type : '') + ' show';
		clearTimeout(el._t);
		el._t = setTimeout(function(){ el.className = 'toast'; }, 2500);
	};
	F.confirm = function(msg, cb) {
		var mask = document.querySelector('.mask');
		var modal = document.querySelector('.confirmmodal');
		if (!mask) { mask = document.createElement('div'); mask.className = 'mask'; document.body.appendChild(mask); }
		if (!modal) {
			modal = document.createElement('div');
			modal.className = 'modal confirmmodal';
			modal.innerHTML = '<div class="mtitle"><i class="fa-solid fa-question-circle"></i> 确认</div><div class="mbody"><p class="confirmtxt"></p></div><div class="mfoot"><button class="btn bcancel">取消</button><button class="btn btnprimary bok">确定</button></div>';
			document.body.appendChild(modal);
			modal.querySelector('.bcancel').onclick = function(){ mask.className='mask'; modal.className='modal confirmmodal'; };
			modal.querySelector('.bok').onclick = function(){ mask.className='mask'; modal.className='modal confirmmodal'; cb(); };
		}
		modal.querySelector('.confirmtxt').textContent = msg;
		mask.className = 'mask show';
		modal.className = 'modal confirmmodal show';
	};
	F.prompt = function(msg, val, cb) {
		var mask = document.querySelector('.mask');
		var modal = document.querySelector('.promptmodal');
		if (!mask) { mask = document.createElement('div'); mask.className = 'mask'; document.body.appendChild(mask); }
		if (!modal) {
			modal = document.createElement('div');
			modal.className = 'modal promptmodal';
			modal.innerHTML = '<div class="mtitle"><i class="fa-solid fa-pencil"></i> <span class="prompttitle"></span></div><div class="mbody"><div class="row"><input class="edt promptedt" value=""></div></div><div class="mfoot"><button class="btn bcancel">取消</button><button class="btn btnprimary bok">确定</button></div>';
			document.body.appendChild(modal);
			modal.querySelector('.bcancel').onclick = function(){ mask.className='mask'; modal.className='modal promptmodal'; };
		}
		modal.querySelector('.prompttitle').textContent = msg;
		var inp = modal.querySelector('.promptedt');
		inp.value = val || '';
		modal.querySelector('.bok').onclick = function(){
			mask.className='mask'; modal.className='modal promptmodal';
			cb(inp.value);
		};
		mask.className = 'mask show';
		modal.className = 'modal promptmodal show';
		setTimeout(function(){
			inp.focus();
			// 选中文件名（不含后缀）
			var v = inp.value;
			var dot = v.lastIndexOf('.');
			if (dot > 0) inp.setSelectionRange(0, dot);
			else inp.select();
		}, 100);
	};
	F.dialog = function(title, html) {
		var mask = document.querySelector('.mask');
		var modal = document.querySelector('.dialogmodal');
		if (!mask) { mask = document.createElement('div'); mask.className = 'mask'; document.body.appendChild(mask); }
		if (!modal) {
			modal = document.createElement('div');
			modal.className = 'modal dialogmodal';
			modal.innerHTML = '<div class="mtitle"><i class="fa-solid fa-window-maximize"></i> <span class="dialogtitle"></span><div class="modalclose"><i class="fa-solid fa-times"></i></div></div><div class="mbody dialogbody"></div><div class="mfoot"><button class="btn btnprimary bok">关闭</button></div>';
			document.body.appendChild(modal);
			modal.querySelector('.bok').onclick = function(){ mask.className='mask'; modal.className='modal dialogmodal'; };
			modal.querySelector('.modalclose').onclick = function(){ mask.className='mask'; modal.className='modal dialogmodal'; };
		}
		modal.querySelector('.dialogtitle').textContent = title;
		modal.querySelector('.dialogbody').innerHTML = html;
		mask.className = 'mask show';
		modal.className = 'modal dialogmodal show';
	};

	// ===== 文件管理器主对象 =====
	var FM = {};
	FM.mgr = {
		wins: [],
		clip: null,
		view: 'detail', // detail | icon
		zidx: 10
	};
	FM.updateClipBtns = function() {
		FM.mgr.wins.forEach(function(w){ FM.win.updatePasteBtn(w); });
		FM.mgr.wins.forEach(function(w){ FM.win.render(w); });
	};

	// ===== 1. 窗口管理 =====
	FM.win = {};

	FM.win.create = function(path, view) {
		var id = 'w' + Date.now() + Math.random().toString(36).slice(2,6);
		var win = {id:id, path:path||'', view:view||FM.mgr.view, el:null, items:[], sel:[], sort:{col:'name',dir:'asc'}};
		var list = document.querySelector('.winlist');
		var hasMulti = FM.mgr.wins.length > 0;
		if (hasMulti) list.className = 'winlist hasmulti';

		// 背景窗口
		var isBg = FM.mgr.wins.length === 0;
		if (isBg) {
			win.el = document.createElement('div');
			win.el.className = 'win winbg';
			win.el.innerHTML =
				'<div class="toolbar">'
				+ '<button class="btn btnsm btnback" title="返回上一级"><i class="fa-solid fa-level-up"></i></button>'
				+ '<div class="sep"></div>'
				+ '<button class="btn btnsm btnvdetail' + (view==='detail'?' active':'') + '" title="详细视图"><i class="fa-solid fa-list"></i></button>'
				+ '<button class="btn btnsm btnvicon' + (view==='icon'?' active':'') + '" title="图标视图"><i class="fa-solid fa-th-large"></i></button>'
				+ '<button class="btn btnsm btnvlarge' + (view==='large'?' active':'') + '" title="大图标视图"><i class="fa-solid fa-image"></i></button>'
				+ '<div class="sep"></div>'
				+ '<button class="btn btnsm btnpaste hide" title="粘贴"><i class="fa-solid fa-paste"></i> 粘贴</button>'
				+ '<button class="btn btnsm btnupload" title="上传文件"><i class="fa-solid fa-upload"></i> 上传</button>'
				+ '<button class="btn btnsm btnmkdir" title="新建文件夹"><i class="fa-solid fa-folder-plus"></i> 新建</button>'
				+ '<div class="sep"></div>'
				+ '<button class="btn btnsm btnzip" title="下载当前目录为ZIP包"><i class="fa-solid fa-file-archive"></i>下载当前目录为ZIP包</button>'
				+ '</div>'
				+ '<div class="addrbar"><div class="crumb"></div></div>'
				+ '<div class="winbody"><div class="filearea"><div class="filehead"></div><div class="filelist detail scroll"></div></div></div>'
				+ '<div class="winstatus"><span class="stitem">0 个项目</span><div class="spacer"></div><span class="stitem stpath">up/</span></div>';
			list.appendChild(win.el);
		} else {
			// 浮动窗口
			win.el = document.createElement('div');
			win.el.className = 'win';
			win.el.style.cssText = 'width:640px;height:460px;left:' + (40 + FM.mgr.wins.length * 30) + 'px;top:' + (40 + FM.mgr.wins.length * 30) + 'px;z-index:' + (++FM.mgr.zidx);
			win.el.innerHTML =
				'<div class="winhead"><div class="wtitle"><i class="ico fa-solid fa-folder"></i> <span class="wpath">' + F.escHtml(path||'/') + '</span></div><div class="wbtns"><button class="wbtn wmax" title="最大化"><i class="fa-solid fa-expand"></i></button><button class="wbtn close" title="关闭"><i class="fa-solid fa-times"></i></button></div></div>'
				+ '<div class="toolbar">'
				+ '<button class="btn btnsm btnback" title="返回上一级"><i class="fa-solid fa-level-up"></i></button>'
				+ '<div class="sep"></div>'
				+ '<button class="btn btnsm btnvdetail"><i class="fa-solid fa-list"></i></button>'
				+ '<button class="btn btnsm btnvicon"><i class="fa-solid fa-th-large"></i></button>'
				+ '<button class="btn btnsm btnvlarge" title="大图标视图"><i class="fa-solid fa-image"></i></button>'
				+ '<div class="sep"></div>'
				+ '<button class="btn btnsm btnpaste hide" title="粘贴"><i class="fa-solid fa-paste"></i> 粘贴</button>'
				+ '<button class="btn btnsm btnupload" title="上传文件"><i class="fa-solid fa-upload"></i> 上传</button>'
				+ '<button class="btn btnsm btnmkdir" title="新建文件夹"><i class="fa-solid fa-folder-plus"></i> 新建</button>'
				+ '<div class="sep"></div>'
				+ '<button class="btn btnsm btnzip" title="下载文件夹为ZIP包"><i class="fa-solid fa-file-archive"></i> ZIP</button>'
				+ '</div>'
				+ '<div class="addrbar"><div class="crumb"></div></div>'
				+ '<div class="winbody"><div class="filearea"><div class="filehead"></div><div class="filelist detail scroll"></div></div></div>'
				+ '<div class="winstatus"><span class="stitem">0 个项目</span><div class="spacer"></div><span class="stitem stpath"></span></div>'
				+ '<div class="reshandle ren"></div><div class="reshandle resn"></div><div class="reshandle rew"></div><div class="reshandle resne"></div><div class="reshandle resnw"></div><div class="reshandle resse"></div><div class="reshandle ressw"></div><div class="reshandle rese"></div><div class="reshandle ress"></div>';
			list.appendChild(win.el);
			// 拖拽
			FM.win.drag(win);
			FM.win.initResize(win);
			// 最大化/关闭
			win.el.querySelector('.wmax').onclick = function(){ FM.win.toggleMax(win); };
			win.el.querySelector('.wbtn.close').onclick = function(){ FM.win.close(win); };
		}

		// 工具栏视图切换
		win.el.querySelector('.btnvdetail').onclick = function(){ FM.win.setView(win, 'detail'); };
		win.el.querySelector('.btnvicon').onclick = function(){ FM.win.setView(win, 'icon'); };
		win.el.querySelector('.btnvlarge').onclick = function(){ FM.win.setView(win, 'large'); };
		// 返回上一级
		win.el.querySelector('.btnback').onclick = function(){ FM.win.goUp(win); };
		// 粘贴按钮
		win.el.querySelector('.btnpaste').onclick = function(){ FM.ctx.exec('paste', {_win:win}); };
		FM.win.updatePasteBtn(win);
		// ZIP按钮
		var btnZip = win.el.querySelector('.btnzip');
		if (btnZip) btnZip.onclick = function(){ 
			var p = win.path || '';
			var n = p ? p.split('/').pop() : '根目录';
			window.open('index.php?act=zip&path=' + encodeURIComponent(p) + '&name=' + encodeURIComponent(n));
		};

		// 工具栏按钮：上传、新建
		var btnUp = win.el.querySelector('.btnupload');
		if (btnUp) btnUp.onclick = function(){ FM.upload.open(win.path); };
		var btnMk = win.el.querySelector('.btnmkdir');
		if (btnMk) btnMk.onclick = function(){ FM.ctx.exec('mkdir', {_win:win}); };

		FM.mgr.wins.push(win);
		FM.win.load(win, path);
		return win;
	};

	FM.win.load = function(win, path) {
		win.path = path || '';
		F.get('list', {path:win.path}, function(r){
			if (!r.ok) { F.toast(r.err||'加载失败', 'err'); return; }
			win.items = r.items || [];
			FM.win.render(win);
			FM.win.updateAddr(win);
			FM.win.updateStatus(win);
		});
	};

	FM.win.render = function(win) {
		var area = win.el.querySelector('.filelist');
		var head = win.el.querySelector('.filehead');
		var isDetail = win.view === 'detail';
		var isLarge = win.view === 'large';
		var isIcon = !isDetail && !isLarge;

		area.className = 'filelist ' + (isDetail ? 'detail scroll' : isLarge ? 'large scroll' : 'icon scroll');
		area.innerHTML = '';

		// 排序（文件夹始终在前）
		var s = win.sort || {col:'name',dir:'asc'};
		var sortKey = s.col, sortDir = s.dir;
		win.items.sort(function(a,b){
			// 文件夹永远排在文件前面
			if (a.type !== b.type) return a.type === 'dir' ? -1 : 1;
			var va, vb;
			if (sortKey === 'name') { va = a.name.toLowerCase(); vb = b.name.toLowerCase(); }
			else if (sortKey === 'size') { va = a.size||0; vb = b.size||0; }
			else if (sortKey === 'ext') { va = a.ext||''; vb = b.ext||''; }
			else { va = a.time||''; vb = b.time||''; }
			if (va === vb) return 0;
			var r = va < vb ? -1 : 1;
			return sortDir === 'asc' ? r : -r;
		});

		// 判断是否在剪贴板（剪切态）
		function isCutting(name) {
			if (!FM.mgr.clip || FM.mgr.clip.type !== 'cut') return false;
			var full = win.path ? win.path + '/' + name : name;
			return (FM.mgr.clip.paths||[]).indexOf(full) !== -1;
		}

		if (isDetail) {
			var sortIcon = sortDir === 'asc' ? 'fa-caret-down' : 'fa-caret-up';
			var cols = [
				{col:'name', label:'名称', style:'flex:1'},
				{col:'size', label:'大小', style:'width:90px;text-align:right'},
				{col:'ext', label:'类型', style:'width:100px'},
				{col:'time', label:'修改日期', style:'width:140px'},
			];
			head.innerHTML = '';
			cols.forEach(function(c){
				var active = s.col === c.col ? ' active' : '';
				var ico = s.col === c.col ? '<i class="sortico fa-solid ' + sortIcon + '"></i>' : '';
				head.innerHTML += '<div class="col col' + c.col + active + '" data-col="' + c.col + '" style="' + c.style + '">' + ico + ' ' + c.label + '</div>';
			});
			head.innerHTML += '<div class="col coldl" style="width:100px;text-align:center;">操作</div>';
			// 列头点击排序（事件委托）
			head.onclick = function(e){
				var col = e.target.closest('[data-col]');
				if (!col) return;
				var c = col.getAttribute('data-col');
				win.sort.dir = (win.sort.col === c && win.sort.dir === 'asc') ? 'desc' : 'asc';
				win.sort.col = c;
				FM.win.render(win);
			};
		} else {
			head.innerHTML = '';
		}

		if (win.items.length === 0) {
			area.innerHTML = '<div class="emptystate"><i class="ico fa-solid fa-folder-open"></i><div class="txt">此文件夹为空</div></div>';
			return;
		}

		// 清除选中中不存在的项
		win.sel = win.sel.filter(function(n){
			for (var i=0;i<win.items.length;i++) if (win.items[i].name===n) return true;
			return false;
		});

		for (var i = 0; i < win.items.length; i++) {
			var v = win.items[i];
			var isDir = v.type === 'dir';
			var iconCls = isDir ? 'folder' : (v.icon||'file');
			var iconFa = F.ficon(iconCls);
			var isSel = win.sel.indexOf(v.name) !== -1;
			var cutting = isCutting(v.name);

			if (isDetail) {
				var row = document.createElement('div');
				row.className = 'frow' + (isSel ? ' sel' : '') + (cutting ? ' cutting' : '');
				row.setAttribute('data-path', v.name);
				row.setAttribute('data-idx', i);
				var dlBtn = isDir ? '<button class="btndl" title="下载为ZIP"><i class="fa-solid fa-file-archive"></i> ZIP</button>' : '<button class="btndl" title="下载"><i class="fa-solid fa-download"></i> 下载</button>';
				var delBtn = '<button class="btndel" title="删除"><i class="fa-solid fa-trash"></i> 删除</button>';
				row.innerHTML =
					'<div class="col colname"><span class="ficon ' + iconCls + '"><i class="fa-solid ' + iconFa + '"></i></span> <span class="fntxt" title="' + F.escHtml(v.name).replace(/"/g,'&quot;') + '">' + F.escHtml(v.name) + '</span></div>'
					+ '<div class="col colsize">' + F.escHtml(v.sizetxt) + '</div>'
					+ '<div class="col coltype">' + (isDir ? '文件夹' : F.escHtml(v.ext).toUpperCase()) + '</div>'
					+ '<div class="col coldate">' + F.escHtml(v.time) + '</div>'
					+ '<div class="col coldl">' + dlBtn + delBtn + '</div>';
				area.appendChild(row);

				(function(row, v, isDir){
					// 仅名称列可拖拽
					var nameCol = row.querySelector('.colname');
					nameCol.draggable = true;
					nameCol.ondragstart = function(e) {
						var p = row.getAttribute('data-path');
						FM.win.doDragStart(e, win, p);
					};

					// 下载按钮点击
					var btnDl = row.querySelector('.btndl');
					if (btnDl) btnDl.onclick = function(e){
						e.stopPropagation();
						var full = win.path ? win.path + '/' + v.name : v.name;
						if (isDir) {
							var name = v.name;
							window.open('index.php?act=zip&path=' + encodeURIComponent(full) + '&name=' + encodeURIComponent(name) + '.zip');
						} else {
							window.open('index.php?act=down&dl=1&path=' + encodeURIComponent(full));
						}
					};
					// 删除按钮点击
					var btnDel = row.querySelector('.btndel');
					if (btnDel) btnDel.onclick = function(e){
						e.stopPropagation();
						var full = win.path ? win.path + '/' + v.name : v.name;
						F.confirm('确定要删除 "' + v.name + '" 吗？', function(){
							F.post('del', {path:full}, function(r){
								if (r.ok) { F.toast('已删除', 'ok'); FM.win.load(win, win.path); }
								else F.toast(r.err||'删除失败', 'err');
							});
						});
					};
				})(row, v, isDir);

				// 文件夹拖拽高亮提示
				if (isDir) {
					row.ondragover = function(e){ e.preventDefault(); this.classList.add('dragfolder'); };
					row.ondragleave = function(){ this.classList.remove('dragfolder'); };
				}

				// 单击选中
				row.onclick = function(e) {
					var p = this.getAttribute('data-path');
					var idx = parseInt(this.getAttribute('data-idx'));
					FM.win.doSelect(win, p, idx, e);
					FM.win.updateSel(win);
				};
				// 双击打开
				row.ondblclick = function(e) {
					var p = this.getAttribute('data-path');
					var full = win.path ? win.path + '/' + p : p;
					var item = FM.getItem(win, p);
					if (item && item.type === 'dir') FM.win.openDir(win, full);
					else FM.preview.open(full);
				};
				// 右键
				row.oncontextmenu = function(e) {
					e.preventDefault();
					var p = this.getAttribute('data-path');
					// 右键时若不在选中集，则单选
					if (win.sel.indexOf(p) === -1) { win.sel = [p]; FM.win.updateSel(win); }
					var full = win.path ? win.path + '/' + p : p;
					var item = FM.getItem(win, p);
					FM.ctx.show(e, win, full, item);
				};

			} else {
				// 图标/大图标视图
				var item = document.createElement('div');
				item.className = 'fitem' + (isSel ? ' sel' : '') + (cutting ? ' cutting' : '');
				item.setAttribute('data-path', v.name);
				item.setAttribute('data-idx', i);
				item.draggable = true;

				// 大图标视图：图片显示缩略图
				var isImg = isLarge && !isDir && /\.(jpg|jpeg|png|gif|bmp|webp|svg)$/i.test(v.name);
				var escName = F.escHtml(v.name);
				if (isLarge) {
					if (isImg) {
						var full = win.path ? win.path + '/' + v.name : v.name;
						item.innerHTML = '<div class="fthumb" style="background-image:url(&quot;index.php?act=down&amp;path=' + encodeURIComponent(full) + '&quot;)"></div><div class="fname" title="' + escName.replace(/"/g,'&quot;') + '">' + escName + '</div>';
					} else {
						item.innerHTML = '<div class="ficon ' + iconCls + '"><i class="fa-solid ' + iconFa + '"></i></div><div class="fname" title="' + escName.replace(/"/g,'&quot;') + '">' + escName + '</div>';
					}
				} else {
					item.innerHTML = '<div class="ficon ' + iconCls + '"><i class="fa-solid ' + iconFa + '"></i></div><div class="fname" title="' + escName.replace(/"/g,'&quot;') + '">' + escName + '</div>';
				}
				area.appendChild(item);

				item.onclick = function(e) {
					var p = this.getAttribute('data-path');
					var idx = parseInt(this.getAttribute('data-idx'));
					FM.win.doSelect(win, p, idx, e);
					FM.win.updateSel(win);
				};
				item.ondblclick = function(e) {
					var p = this.getAttribute('data-path');
					var full = win.path ? win.path + '/' + p : p;
					var item = FM.getItem(win, p);
					if (item && item.type === 'dir') FM.win.openDir(win, full);
					else FM.preview.open(full);
				};
				item.oncontextmenu = function(e) {
					e.preventDefault();
					var p = this.getAttribute('data-path');
					if (win.sel.indexOf(p) === -1) { win.sel = [p]; FM.win.updateSel(win); }
					var full = win.path ? win.path + '/' + p : p;
					var item = FM.getItem(win, p);
					FM.ctx.show(e, win, full, item);
				};
				item.ondragstart = function(e) {
					var p = this.getAttribute('data-path');
					FM.win.doDragStart(e, win, p);
				};
				// 文件夹拖拽高亮提示
				if (isDir) {
					item.ondragover = function(e){ e.preventDefault(); this.classList.add('dragfolder'); };
					item.ondragleave = function(){ this.classList.remove('dragfolder'); };
				}
			}
		}
	};
 
	// 多选逻辑
	FM.win.doSelect = function(win, name, idx, e) {
		if (e.ctrlKey || e.metaKey) {
			// Ctrl: 切换
			var pos = win.sel.indexOf(name);
			if (pos === -1) win.sel.push(name);
			else win.sel.splice(pos, 1);
		} else if (e.shiftKey && win._lastSel !== undefined) {
			// Shift: 范围选择
			var from = Math.min(win._lastSel, idx);
			var to = Math.max(win._lastSel, idx);
			win.sel = [];
			for (var i = from; i <= to; i++) win.sel.push(win.items[i].name);
		} else {
			// 单选
			win.sel = [name];
		}
		win._lastSel = idx;
	};

	// 轻量更新选中态（仅切换 class，不销毁 DOM）
	FM.win.updateSel = function(win) {
		win.el.querySelectorAll('.frow.sel, .fitem.sel').forEach(function(el){ el.classList.remove('sel'); });
		win.el.querySelectorAll('.frow, .fitem').forEach(function(el){
			var name = el.getAttribute('data-path');
			if (win.sel.indexOf(name) !== -1) el.classList.add('sel');
		});
	};

	FM.win.updatePasteBtn = function(win) {
		var btn = win.el.querySelector('.btnpaste');
		if (!btn) return;
		if (FM.mgr.clip) btn.className = 'btn btnsm btnpaste';
		else btn.className = 'btn btnsm btnpaste hide';
	};

	FM.win.updateAddr = function(win) {
		var crumb = win.el.querySelector('.crumb');
		var parts = win.path ? win.path.split('/') : [];
		var html = '<a class="root" data-path="">根目录</a>';
		var cur = '';
		for (var i = 0; i < parts.length; i++) {
			cur = cur ? cur + '/' + parts[i] : parts[i];
			html += '<span class="sep">/</span><a data-path="' + cur + '">' + F.escHtml(parts[i]) + '</a>';
		}
		crumb.innerHTML = html;
		// 面包屑链接点击直接导航
		crumb.querySelectorAll('a').forEach(function(a){
			a.onclick = function(e){
				e.stopPropagation();
				FM.win.openDir(win, this.getAttribute('data-path'));
			};
		});
		// 面包屑空白区域点击进入编辑模式
		crumb.onclick = function(e) {
			if (e.target.tagName === 'A') return;
			var full = win.path || '';
			crumb.innerHTML = '<input class="edt crumbedt" value="' + F.escHtml(full) + '" style="width:100%;">';
			var inp = crumb.querySelector('.crumbedt');
			inp.focus();
			inp.select();
			inp.onkeydown = function(ev) {
				if (ev.key === 'Enter') {
					var v = inp.value.trim().replace(/^\/+|\/+$/g, '');
					if (v === win.path) { FM.win.updateAddr(win); return; }
					F.get('list', {path:v}, function(r){
						if (!r.ok) { F.toast('路径不存在: up/' + v, 'err'); FM.win.updateAddr(win); }
						else { FM.win.openDir(win, v); }
					});
				}
				if (ev.key === 'Escape') FM.win.updateAddr(win);
			};
			inp.onblur = function() { FM.win.updateAddr(win); };
		};
		// 路径显示
		win.el.querySelector('.stpath').textContent = win.path ? 'up/' + win.path : 'up/';
	};

	FM.win.updateStatus = function(win) {
		var cnt = win.items ? win.items.length : 0;
		var dirs = 0, files = 0;
		(win.items||[]).forEach(function(v){ v.type==='dir' ? dirs++ : files++; });
		var txt = cnt + ' 个项目';
		if (dirs) txt += '（' + dirs + ' 个文件夹）';
		if (files) txt += '（' + files + ' 个文件）';
		win.el.querySelector('.stitem:first-child').textContent = txt;
	};

	FM.win.openDir = function(win, path) {
		FM.win.load(win, path);
		// 更新浮动窗口标题
		var wpath = win.el.querySelector('.wpath');
		if (wpath) wpath.textContent = path || '/';
		// 背景窗口路径变化时更新 URL hash
		if (FM.mgr.wins.length > 0 && win === FM.mgr.wins[0]) {
			if (path) location.hash = '#' + encodeURIComponent(path);
			else location.hash = '';
		}
	};

	FM.win.goUp = function(win) {
		if (!win.path) return;
		var parts = win.path.split('/');
		parts.pop();
		var parent = parts.join('/');
		FM.win.openDir(win, parent);
	};

	FM.win.setView = function(win, view) {
		win.view = view;
		FM.mgr.view = view;
		localStorage.setItem('fm_view', view);
		win.el.querySelector('.btnvdetail').className = 'btn btnsm btnvdetail' + (view==='detail'?' active':'');
		win.el.querySelector('.btnvicon').className = 'btn btnsm btnvicon' + (view==='icon'?' active':'');
		win.el.querySelector('.btnvlarge').className = 'btn btnsm btnvlarge' + (view==='large'?' active':'');
		FM.win.render(win);
	};

	FM.win.close = function(win) {
		var idx = FM.mgr.wins.indexOf(win);
		if (idx > 0) {
			FM.mgr.wins.splice(idx, 1);
			win.el.parentNode.removeChild(win.el);
			if (FM.mgr.wins.length <= 1) {
				document.querySelector('.winlist').className = 'winlist';
			}
		}
	};

	FM.win.toggleMax = function(win) {
		var el = win.el;
		if (el.classList.contains('max')) {
			el.classList.remove('max');
			el.style.cssText = win._pos || 'width:640px;height:460px;left:60px;top:60px';
		} else {
			win._pos = el.style.cssText;
			el.classList.add('max');
			el.style.cssText = '';
		}
	};

	FM.win.drag = function(win) {
		var head = win.el.querySelector('.winhead');
		var isDown = false, ox, oy;
		head.onmousedown = function(e) {
			if (e.target.closest('.wbtns')) return;
			isDown = true;
			ox = e.clientX - win.el.offsetLeft;
			oy = e.clientY - win.el.offsetTop;
			win.el.style.zIndex = ++FM.mgr.zidx;
			document.addEventListener('mousemove', onMove);
			document.addEventListener('mouseup', onUp);
		};
		function onMove(e) {
			if (!isDown) return;
			var l = Math.max(0, e.clientX - ox);
			var t = Math.max(0, e.clientY - oy);
			win.el.style.left = l + 'px';
			win.el.style.top = t + 'px';
		}
		function onUp() {
			isDown = false;
			document.removeEventListener('mousemove', onMove);
			document.removeEventListener('mouseup', onUp);
		}
	};

	// 窗口调整大小
	FM.win.initResize = function(win) {
		if (win._resized) return;
		win._resized = true;
		var el = win.el;
		var handles = el.querySelectorAll('.reshandle');
		var dirs = {resn:'n', ress:'s', rew:'w', rese:'e', resnw:'nw', resne:'ne', ressw:'sw', resse:'se'};
		handles.forEach(function(h){
			for (var cls in dirs) {
				if (h.classList.contains(cls)) { h._dir = dirs[cls]; break; }
			}
			h.onmousedown = function(e) {
				e.preventDefault();
				var dir = this._dir;
				var sx = e.clientX, sy = e.clientY;
				var sw = el.offsetWidth, sh = el.offsetHeight;
				var sl = el.offsetLeft, st = el.offsetTop;
				var minW = 360, minH = 200;
				el.style.zIndex = ++FM.mgr.zidx;
				function onMove(ev) {
					var dx = ev.clientX - sx, dy = ev.clientY - sy;
					var w = sw, h = sh, l = sl, t = st;
					if (dir.indexOf('e') !== -1) w = Math.max(minW, sw + dx);
					if (dir.indexOf('s') !== -1) h = Math.max(minH, sh + dy);
					if (dir.indexOf('w') !== -1) { w = Math.max(minW, sw - dx); l = sl + (sw - w); }
					if (dir.indexOf('n') !== -1) { h = Math.max(minH, sh - dy); t = st + (sh - h); }
					el.style.width = w + 'px';
					el.style.height = h + 'px';
					el.style.left = l + 'px';
					el.style.top = t + 'px';
				}
				function onUp() {
					document.removeEventListener('mousemove', onMove);
					document.removeEventListener('mouseup', onUp);
				}
				document.addEventListener('mousemove', onMove);
				document.addEventListener('mouseup', onUp);
			};
		});
	};

	FM.getItem = function(win, name) {
		for (var i = 0; i < win.items.length; i++) {
			if (win.items[i].name === name) return win.items[i];
		}
		return null;
	};

	// ===== 拖拽移动 =====
	FM.dnd = {};
	FM.dnd.data = null;

	FM.win.doDragStart = function(e, win, name) {
		var paths;
		// 如果拖拽的项在选中集，拖动所有选中项
		if (win.sel.indexOf(name) !== -1) {
			paths = win.sel.map(function(n){ return win.path ? win.path + '/' + n : n; });
		} else {
			paths = [win.path ? win.path + '/' + name : name];
			win.sel = [name];
			FM.win.updateSel(win);
		}
		FM.dnd.data = {paths:paths, fromWin:win};
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData('text/plain', paths[0]); // 必需，否则 FF/Chrome 不启动拖拽
		// 虚影
		var ghost = document.getElementById('dragghost');
		ghost.textContent = paths.length + ' 项';
		ghost.className = 'dragghost show';
		ghost.offsetHeight; // 强制 reflow，让浏览器渲染后再截图
		e.dataTransfer.setDragImage(ghost, 20, 16);

		// 注册全局 drop 事件
		document.addEventListener('dragover', FM.dnd.onDocOver);
		document.addEventListener('drop', FM.dnd.onDocDrop);
	};

	FM.dnd.onDocOver = function(e) {
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';
		// 拖入文件区时高亮（源窗口除外）
		var el = e.target;
		while (el) {
			if (el.classList && el.classList.contains('filelist')) {
				// 检查是否是源窗口
				var srcWin = FM.dnd.data ? FM.dnd.data.fromWin : null;
				if (srcWin && srcWin.el.contains(el)) break;
				el.classList.add('dragover');
				break;
			}
			el = el.parentNode;
		}
	};

	FM.dnd.onDocDrop = function(e) {
		e.preventDefault();
		document.getElementById('dragghost').className = 'dragghost';
		document.removeEventListener('dragover', FM.dnd.onDocOver);
		document.removeEventListener('drop', FM.dnd.onDocDrop);
		// 清除所有高亮
		document.querySelectorAll('.dragover').forEach(function(el){ el.classList.remove('dragover'); });
		document.querySelectorAll('.dragfolder').forEach(function(el){ el.classList.remove('dragfolder'); });

		var data = FM.dnd.data;
		if (!data || !data.paths || data.paths.length === 0) return;
		FM.dnd.data = null;

		// 找到目标窗口和路径
		var targetWin = FM.win.findWin(e.clientX, e.clientY);
		if (!targetWin) return;

		// 解析目标文件夹路径
		var targetPath = targetWin.path || '';
		// 如果 drop 在某个文件夹上，进入该文件夹
		var el = e.target;
		while (el) {
			if (el.classList && el.classList.contains('frow')) {
				var n = el.getAttribute('data-path');
				var item = FM.getItem(targetWin, n);
				if (item && item.type === 'dir') {
					targetPath = targetPath ? targetPath + '/' + n : n;
					break;
				}
			}
			if (el.classList && el.classList.contains('fitem')) {
				var n = el.getAttribute('data-path');
				var item = FM.getItem(targetWin, n);
				if (item && item.type === 'dir') {
					targetPath = targetPath ? targetPath + '/' + n : n;
					break;
				}
			}
			el = el.parentNode;
		}

		// 如果源和目标在同一目录，跳过
		var sameDir = true;
		for (var i = 0; i < data.paths.length; i++) {
			var p = data.paths[i];
			var dir = p.lastIndexOf('/') > 0 ? p.slice(0, p.lastIndexOf('/')) : '';
			if (dir !== targetPath) { sameDir = false; break; }
		}
		if (sameDir && data.fromWin === targetWin) { FM.dnd.data = null; return; }

		// 依次移动文件
		var moved = 0;
		(function next(i){
			if (i >= data.paths.length) {
				FM.mgr.clip = null;
				if (moved > 0) {
					F.toast('已移动 ' + moved + ' 项', 'ok');
					if (data.fromWin) FM.win.load(data.fromWin, data.fromWin.path);
					FM.win.load(targetWin, targetWin.path);
				}
				FM.updateClipBtns();
				return;
			}
			var p = data.paths[i];
			F.post('paste', {path:targetPath, src:p, type:'cut'}, function(r){
				if (r.ok) moved++;
				next(i+1);
			});
		})(0);
	};

	FM.win.findWin = function(cx, cy) {
		var hits = [];
		for (var i = 0; i < FM.mgr.wins.length; i++) {
			var r = FM.mgr.wins[i].el.getBoundingClientRect();
			if (cx >= r.left && cx <= r.right && cy >= r.top && cy <= r.bottom) {
				hits.push({win:FM.mgr.wins[i], z:i});
			}
		}
		if (hits.length === 0) return null;
		// 返回最上层窗口
		hits.sort(function(a,b){ return b.z - a.z; });
		return hits[0].win;
	};
	// ===== 2. 右键菜单 =====
	FM.ctx = {};
	FM.ctx.show = function(e, win, path, item) {
		e.preventDefault();
		var menu = document.getElementById('ctxmenu');
		var isDir = item && item.type === 'dir';
		var isBlank = !item;
		var multi = win.sel && win.sel.length > 1;
		var label = multi ? win.sel.length + ' 项' : (item ? item.name : '');
		var html = '';

		if (isBlank) {
			html += '<div class="mitem" data-act="mkdir"><i class="ico fa-solid fa-folder-plus"></i> 新建文件夹</div>';
			html += '<div class="mitem" data-act="refresh"><i class="ico fa-solid fa-refresh"></i> 刷新</div>';
			html += '<div class="msep"></div>';
			html += '<div class="mitem' + (FM.mgr.clip?'':' dis') + '" data-act="paste"><i class="ico fa-solid fa-paste"></i> 粘贴</div>';
		} else if (isDir && !multi) {
			html += '<div class="mitem" data-act="enter"><i class="ico fa-solid fa-folder-open"></i> 进入</div>';
			html += '<div class="mitem" data-act="newwin"><i class="ico fa-solid fa-window-restore"></i> 新窗口打开</div>';
			html += '<div class="msep"></div>';
			html += '<div class="mitem" data-act="ren"><i class="ico fa-solid fa-pencil"></i> 重命名</div>';
			html += '<div class="mitem danger" data-act="del"><i class="ico fa-solid fa-trash"></i> 删除</div>';
			html += '<div class="msep"></div>';
			html += '<div class="mitem" data-act="copyziplink"><i class="ico fa-solid fa-link"></i> 复制ZIP链接</div>';
			html += '<div class="msep"></div>';
			html += '<div class="mitem" data-act="zip"><i class="ico fa-solid fa-file-archive"></i> 下载为ZIP包</div>';
			html += '<div class="msep"></div>';
			html += '<div class="mitem" data-act="prop"><i class="ico fa-solid fa-info-circle"></i> 属性</div>';
		} else {
			if (!multi) {
				html += '<div class="mitem" data-act="down"><i class="ico fa-solid fa-download"></i> 下载</div>';
				html += '<div class="mitem" data-act="preview"><i class="ico fa-solid fa-eye"></i> 预览</div>';
				html += '<div class="mitem" data-act="copylink"><i class="ico fa-solid fa-link"></i> 复制链接</div>';
				html += '<div class="msep"></div>';
			}
			html += '<div class="mitem" data-act="zipsel"><i class="ico fa-solid fa-file-archive"></i> ' + (multi ? '下载 ' + win.sel.length + ' 项为ZIP包' : '下载为ZIP包') + '</div>';
			html += '<div class="msep"></div>';
			html += '<div class="mitem danger" data-act="del"><i class="ico fa-solid fa-trash"></i> ' + (multi ? '删除 ' + win.sel.length + ' 项' : '删除') + '</div>';
			html += '<div class="msep"></div>';
			html += '<div class="mitem" data-act="cut"><i class="ico fa-solid fa-scissors"></i> ' + (multi ? '剪切 ' + win.sel.length + ' 项' : '剪切') + '</div>';
			html += '<div class="mitem" data-act="copy"><i class="ico fa-solid fa-copy"></i> ' + (multi ? '复制 ' + win.sel.length + ' 项' : '复制') + '</div>';
			html += '<div class="msep"></div>';
			html += '<div class="mitem' + (FM.mgr.clip?'':' dis') + '" data-act="paste"><i class="ico fa-solid fa-paste"></i> 粘贴</div>';
			if (!multi) {
				html += '<div class="msep"></div>';
				html += '<div class="mitem" data-act="prop"><i class="ico fa-solid fa-info-circle"></i> 属性</div>';
			}
		}
		menu.innerHTML = html;
		menu._win = win;
		menu._path = path;
		menu._item = item;

		// 点击处理
		menu.querySelectorAll('.mitem:not(.dis)').forEach(function(m){
			m.onclick = function(){ FM.ctx.exec(this.getAttribute('data-act'), menu); };
		});

		// 定位
		var mx = Math.min(e.clientX, window.innerWidth - 200);
		var my = Math.min(e.clientY, window.innerHeight - 200);
		menu.style.left = mx + 'px';
		menu.style.top = my + 'px';
		menu.className = 'ctxmenu show';

		// 点击其他地方关闭
		setTimeout(function(){
			document.addEventListener('click', FM.ctx.hide, {once:true});
		}, 10);
	};
	FM.ctx.hide = function() {
		document.getElementById('ctxmenu').className = 'ctxmenu';
	};
	FM.ctx.exec = function(act, menu) {
		FM.ctx.hide();
		var win = menu._win;
		var path = menu._path;
		var item = menu._item;
		var multi = win.sel && win.sel.length > 1;

		// 获取操作路径列表
		function getPaths() {
			if (multi) return win.sel.map(function(n){ return win.path ? win.path + '/' + n : n; });
			return [path];
		}

		switch (act) {
			case 'down':
				window.open('index.php?act=down&dl=1&path=' + encodeURIComponent(path));
				break;
			case 'preview':
				FM.preview.open(path);
				break;
			case 'del':
				if (multi) {
					var ps = getPaths();
					F.confirm('确定要删除选中的 ' + ps.length + ' 项吗？', function(){
						var done = 0;
						(function next(i){
							if (i >= ps.length) { F.toast('已删除 ' + done + ' 项', 'ok'); FM.win.load(win, win.path); return; }
							F.post('del', {path:ps[i]}, function(r){ if(r.ok) done++; next(i+1); });
						})(0);
					});
				} else {
					F.confirm('确定要删除 "' + item.name + '" 吗？', function(){
						F.post('del', {path:path}, function(r){
							if (r.ok) { F.toast('已删除', 'ok'); FM.win.load(win, win.path); }
							else F.toast(r.err||'删除失败', 'err');
						});
					});
				}
				break;
			case 'ren':
				if (multi) {
					F.toast('暂不支持批量重命名', 'err');
				} else {
					F.prompt('重命名', item.name, function(v){
						if (v && v !== item.name) {
							F.post('ren', {path:path, name:v}, function(r){
								if (r.ok) { F.toast('已重命名', 'ok'); FM.win.load(win, win.path); }
								else F.toast(r.err||'重命名失败', 'err');
							});
						}
					});
				}
				break;
			case 'cut':
				if (multi) {
					FM.mgr.clip = {type:'cut', paths:getPaths()};
					F.toast('已剪切 ' + win.sel.length + ' 项');
				} else {
					FM.mgr.clip = {type:'cut', paths:[path]};
					F.toast('已剪切: ' + item.name);
				}
				FM.updateClipBtns();
				break;
			case 'copy':
				if (multi) {
					FM.mgr.clip = {type:'copy', paths:getPaths()};
					F.toast('已复制 ' + win.sel.length + ' 项');
				} else {
					FM.mgr.clip = {type:'copy', paths:[path]};
					F.toast('已复制: ' + item.name);
				}
				FM.updateClipBtns();
				break;
			case 'paste':
				if (!FM.mgr.clip) { F.toast('剪贴板为空', 'err'); return; }
				var ps = FM.mgr.clip.paths || [];
				var ptype = FM.mgr.clip.type;
				var done = 0;
				(function next(i){
					if (i >= ps.length) {
						FM.mgr.clip = null;
						if (done > 0) { F.toast('已粘贴 ' + done + ' 项', 'ok'); FM.win.load(win, win.path); }
						else F.toast('粘贴失败', 'err');
						FM.updateClipBtns();
						return;
					}
					(function(j){
						var p = ps[j];
						F.post('paste', {path:win.path||'', src:p, type:ptype}, function(r){
							if (r.ok) done++;
							next(j+1);
						});
					})(i);
				})(0);
				break;
			case 'mkdir':
				F.prompt('新建文件夹', '', function(v){
					if (v) {
						var p = win.path ? win.path + '/' + v : v;
						F.post('mkdir', {path:p}, function(r){
							if (r.ok) { F.toast('已创建', 'ok'); FM.win.load(win, win.path); }
							else F.toast(r.err||'创建失败', 'err');
						});
					}
				});
				break;
			case 'refresh':
				FM.win.load(win, win.path);
				break;
			case 'zip':
				window.open('index.php?act=zip&paths=' + encodeURIComponent(path) + '&name=' + encodeURIComponent(path.split('/').pop()||'download') + '.zip');
				break;
			case 'zipsel':
				var names = win.sel.map(function(n){ return win.path ? win.path + '/' + n : n; });
				var zipName = win.sel.length === 1 ? (win.sel[0]) : ('selection');
				var q = names.map(function(n){ return encodeURIComponent(n); }).join(',');
				window.open('index.php?act=zip&paths=' + q + '&name=' + encodeURIComponent(zipName) + '.zip');
				break;
			case 'prop':
				var p = path;
				F.get('info', {path:p}, function(r){
					if (!r.ok || !r.info) { F.toast('获取属性失败', 'err'); return; }
					var info = r.info;
					var html = '<div style="padding:16px;min-width:320px;">';
					html += '<div style="margin-bottom:12px;font-size:16px;font-weight:600;">' + F.escHtml(info.name||'') + '</div>';
					html += '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
					html += '<tr><td style="padding:6px 10px;color:#6b7280;width:80px;">类型</td><td style="padding:6px 10px;">' + (info.isdir ? '文件夹' : F.escHtml(info.ext||'').toUpperCase()) + '</td></tr>';
					if (info.isdir && info.file_count != null) html += '<tr><td style="padding:6px 10px;color:#6b7280;">文件数</td><td style="padding:6px 10px;">' + info.file_count + ' 项</td></tr>';
					if (info.isdir && info.total_size) html += '<tr><td style="padding:6px 10px;color:#6b7280;">总大小</td><td style="padding:6px 10px;">' + F.escHtml(info.total_size) + '</td></tr>';
					if (!info.isdir && info.sizetxt) html += '<tr><td style="padding:6px 10px;color:#6b7280;">大小</td><td style="padding:6px 10px;">' + F.escHtml(info.sizetxt) + '</td></tr>';
					if (info.time) html += '<tr><td style="padding:6px 10px;color:#6b7280;">修改时间</td><td style="padding:6px 10px;">' + F.escHtml(info.time) + '</td></tr>';
					if (info.ctime) html += '<tr><td style="padding:6px 10px;color:#6b7280;">创建时间</td><td style="padding:6px 10px;">' + F.escHtml(info.ctime) + '</td></tr>';
					if (info.width && info.height) html += '<tr><td style="padding:6px 10px;color:#6b7280;">尺寸</td><td style="padding:6px 10px;">' + info.width + ' × ' + info.height + '</td></tr>';
					html += '</table></div>';
					F.dialog('属性', html);
				});
				break;
		case 'enter':
				FM.win.openDir(win, path);
				break;
			case 'newwin':
				FM.win.create(path, win.view);
				break;
			case 'copylink':
				var link = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '/') + 'index.php?act=down&dl=1&path=' + encodeURIComponent(path);
				navigator.clipboard.writeText(link).then(function(){
					F.toast('链接已复制', 'ok');
				}).catch(function(){
					// fallback
					var ta = document.createElement('textarea');
					ta.value = link;
					document.body.appendChild(ta);
					ta.select();
					document.execCommand('copy');
					document.body.removeChild(ta);
					F.toast('链接已复制', 'ok');
				});
				break;
			case 'copyziplink':
				var name = path.split('/').pop() || 'download';
				var zlink = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '/') + 'index.php?act=zip&path=' + encodeURIComponent(path) + '&name=' + encodeURIComponent(name) + '.zip';
				navigator.clipboard.writeText(zlink).then(function(){
					F.toast('ZIP链接已复制', 'ok');
				}).catch(function(){
					var ta = document.createElement('textarea');
					ta.value = zlink;
					document.body.appendChild(ta);
					ta.select();
					document.execCommand('copy');
					document.body.removeChild(ta);
					F.toast('ZIP链接已复制', 'ok');
				});
				break;
		}
	};

	// ===== 3. 上传 =====
	FM.upload = {};
	FM.upload.open = function(dir) {
		var inp = document.getElementById('fileinput');
		inp.setAttribute('data-dir', dir||'');
		inp.click();
	};
	FM.upload.tasks = [];
	FM.upload.panel = null;

	FM.upload.addTask = function(file, dir) {
		var id = 'up' + Date.now() + '_' + FM.upload.tasks.length;
		var task = {id:id, name:file.name, size:file.size, loaded:0, xhr:null, status:'waiting'};
		FM.upload.tasks.push(task);
		FM.upload.showPanel();
		FM.upload.render();
		FM.upload.startTask(task, file, dir);
	};

	FM.upload.startTask = function(task, file, dir) {
		task.status = 'uploading';
		var xhr = new XMLHttpRequest();
		task.xhr = xhr;
		var fd = new FormData();
		fd.append('files', file);
		fd.append('dir', dir||'');

		xhr.upload.onprogress = function(e) {
			if (e.lengthComputable) {
				task.loaded = e.loaded;
				task.total = e.total;
				FM.upload.render();
			}
		};
		xhr.onload = function() {
			try {
				var r = JSON.parse(xhr.responseText);
				task.status = r.ok ? 'done' : 'err';
				task.err = r.err || '';
			} catch(e) { task.status = 'err'; task.err = '解析失败'; }
			FM.upload.render();
			FM.win.load(FM.mgr.wins[0], FM.mgr.wins[0].path);
		};
		xhr.onerror = function() { task.status = 'err'; task.err = '网络错误'; FM.upload.render(); };
		xhr.open('POST', 'index.php?act=upload', true);
		xhr.send(fd);
	};

	FM.upload.cancel = function(task) {
		if (task.xhr) task.xhr.abort();
		task.status = 'cancelled';
		FM.upload.render();
	};

	FM.upload.showPanel = function() {
		var p = document.querySelector('.uploadpanel');
		p.className = 'uploadpanel';
		FM.upload.panel = p;
		p.querySelector('.close').onclick = function(){ p.className = 'uploadpanel hide'; };
		p.querySelector('.clear').onclick = function(){
			FM.upload.tasks = FM.upload.tasks.filter(function(t){ return t.status==='uploading'||t.status==='waiting'; });
			FM.upload.render();
			if (FM.upload.tasks.length === 0) p.className = 'uploadpanel hide';
		};
	};

	FM.upload.render = function() {
		var tasks = FM.upload.tasks;
		var body = document.querySelector('.upbody');
		var totalEl = document.querySelector('.uptotal');
		var countEl = document.querySelector('.upcount');
		body.innerHTML = '';
		var done = 0;
		for (var i = 0; i < tasks.length; i++) {
			var t = tasks[i];
			if (t.status === 'done') done++;
			var pct = t.total ? Math.round(t.loaded / t.total * 100) : 0;
			var item = document.createElement('div');
			item.className = 'upitem' + (t.status==='done'?' done':'') + (t.status==='err'?' err':'');
			var errMsg = (t.status==='err' && t.err) ? '<div class="uperr">' + F.escHtml(t.err) + '</div>' : '';
			item.innerHTML =
				'<div class="upinfo"><i class="ficon fa-solid fa-file"></i><span class="upname">' + F.escHtml(t.name) + '</span><span class="upsize">' + F.sizeTxt(t.loaded) + '/' + F.sizeTxt(t.size) + '</span>'
				+ (t.status==='uploading'||t.status==='waiting' ? '<button class="upcancel" data-id="' + t.id + '"><i class="fa-solid fa-times"></i></button>' : '')
				+ '</div>'
				+ '<div class="upbar"><div class="upfill" style="width:' + (t.status==='done'?100:t.status==='err'?100:pct) + '%"></div></div>'
				+ errMsg;
			body.appendChild(item);
			var cancelBtn = item.querySelector('.upcancel');
			if (cancelBtn) {
				cancelBtn.onclick = function(){
					var tid = this.getAttribute('data-id');
					for (var j = 0; j < FM.upload.tasks.length; j++) {
						if (FM.upload.tasks[j].id === tid) { FM.upload.cancel(FM.upload.tasks[j]); break; }
					}
				};
			}
		}
		countEl.textContent = tasks.length;
		totalEl.innerHTML = '<span>共 ' + tasks.length + ' 个任务</span><span>已完成 ' + done + '</span>';

		// 全部完成后自动关闭
		var allDone = tasks.every(function(t){ return t.status==='done'||t.status==='err'||t.status==='cancelled'; });
		if (allDone && tasks.length > 0) {
			setTimeout(function(){ document.querySelector('.uploadpanel').className = 'uploadpanel hide'; }, 1500);
		}
	};

	// ===== 4. 预览 =====
	FM.preview = {};
	FM.preview.win = null;

	FM.preview.initViewer = function(body) {
		if (!FM.preview.viewer) {
			FM.preview.imgEl = document.createElement('img');
			FM.preview.imgEl.style.cssText = 'display:none;';
			body.appendChild(FM.preview.imgEl);
			var that = this;
			FM.preview.viewer = new Viewer(FM.preview.imgEl, {
				inline:true,
				transition:false,
				zoomRatio:0.3,
				navbar:false,
				toolbar:{
					zoomIn:1, zoomOut:1, oneToOne:1, reset:1,
					rotateLeft:1, rotateRight:1,
					flipHorizontal:1, flipVertical:1,
				},
			});
			// 窗口 resize 时同步 viewer
			FM.preview._onResize = function(){ if (FM.preview.viewer) FM.preview.viewer.resize(); };
			window.addEventListener('resize', FM.preview._onResize);
		}
		return FM.preview.viewer;
	};

	FM.preview.destroyViewer = function() {
		if (FM.preview._onResize) { window.removeEventListener('resize', FM.preview._onResize); FM.preview._onResize = null; }
		if (FM.preview.viewer) {
			try { FM.preview.viewer.destroy(); } catch(e) {}
			FM.preview.viewer = null;
			FM.preview.imgEl = null;
		}
	};

	FM.preview.close = function() {
		// 停止所有视频/音频播放
		if (FM.preview.win) {
			var pvb = FM.preview.win.querySelector('.pvbody');
			if (pvb) pvb.querySelectorAll('video, audio').forEach(function(m){ m.pause(); delete m.src; });
		}
		FM.preview.destroyViewer();
		if (FM.preview.win) FM.preview.win.style.display = 'none';
	};

	// 保存预览窗口大小/位置
	FM.preview._saveState = function() {
		var el = FM.preview.win;
		if (!el) return;
		FM.preview._state = {
			w: el.offsetWidth,
			h: el.offsetHeight,
			r: parseInt(el.style.right) || 20,
			t: parseInt(el.style.top) || 60,
		};
	};

	FM.preview._initResize = function(el) {
		var dirs = {resnw:'nw', resne:'ne', ressw:'sw', resse:'se', resn:'n', ress:'s', rew:'w', rese:'e'};
		el.querySelectorAll('.reshandle').forEach(function(h){
			var dir = dirs[Object.keys(dirs).find(function(k){ return h.classList.contains(k); })];
			if (!dir) return;
			h.onmousedown = function(e){
				e.preventDefault();
				var sx = e.clientX, sy = e.clientY;
				var sw = el.offsetWidth, sh = el.offsetHeight;
				var sl = el.offsetLeft, st = el.offsetTop;
				var minW = 300, minH = 200;
				function onMove(ev){
					var dx = ev.clientX - sx, dy = ev.clientY - sy;
					var w = sw, h = sh, l = sl, t = st;
					if (dir.indexOf('e') !== -1) w = Math.max(minW, sw + dx);
					if (dir.indexOf('s') !== -1) h = Math.max(minH, sh + dy);
					if (dir.indexOf('w') !== -1) { w = Math.max(minW, sw - dx); l = sl + (sw - w); }
					if (dir.indexOf('n') !== -1) { h = Math.max(minH, sh - dy); t = st + (sh - h); }
					el.style.width = w + 'px';
					el.style.height = h + 'px';
					el.style.left = l + 'px';
					el.style.top = t + 'px';
					el.style.right = 'auto';
				}
				function onUp(){
					document.removeEventListener('mousemove', onMove);
					document.removeEventListener('mouseup', onUp);
					if (FM.preview.viewer) FM.preview.viewer.resize();
					FM.preview._saveState();
				}
				document.addEventListener('mousemove', onMove);
				document.addEventListener('mouseup', onUp);
			};
		});
	};

	FM.preview.open = function(path) {
		if (!FM.preview.win) {
			var state = FM.preview._state || {w:600, h:440, r:20, t:60};
			var el = document.createElement('div');
			el.className = 'previewwin';
			el.style.cssText = 'width:' + state.w + 'px;height:' + state.h + 'px;right:' + state.r + 'px;top:' + state.t + 'px;';
			el.innerHTML =
				'<div class="pvhead"><div class="pvtitle"><i class="ico fa-solid fa-eye"></i> <span class="pvpname"></span></div><div class="pvbtns"><button class="pvbtn close" title="关闭"><i class="fa-solid fa-times"></i></button></div></div>'
				+ '<div class="pvbody"></div>'
				+ '<div class="pvfoot"><span class="pvpath"></span><span class="spacer"></span><span class="pvinfo"></span></div>'
				+ '<div class="reshandle resnw"></div><div class="reshandle resne"></div><div class="reshandle ressw"></div><div class="reshandle resse"></div>'
				+ '<div class="reshandle resn"></div><div class="reshandle ress"></div><div class="reshandle rew"></div><div class="reshandle rese"></div>';
			document.body.appendChild(el);

			// 关闭
			el.querySelector('.close').onclick = function(){
				FM.preview.close();
			};
			// 拖拽（保存位置）
			(function(){
				var head = el.querySelector('.pvhead');
				var isDown = false, ox, oy;
				head.onmousedown = function(e) {
					if (e.target.closest('.pvbtns')) return;
					isDown = true;
					ox = e.clientX - el.offsetLeft;
					oy = e.clientY - el.offsetTop;
					document.addEventListener('mousemove', onMove);
					document.addEventListener('mouseup', onUp);
				};
				function onMove(e) {
					if (!isDown) return;
					el.style.left = (e.clientX-ox)+'px';
					el.style.top = (e.clientY-oy)+'px';
					el.style.right = 'auto';
				}
				function onUp() {
					isDown = false;
					document.removeEventListener('mousemove', onMove);
					document.removeEventListener('mouseup', onUp);
					FM.preview._saveState();
				}
			})();
			// 调整大小
			FM.preview._initResize(el);

			FM.preview.win = el;
		}

		var win = FM.preview.win;
		win.style.display = 'flex';
		win.querySelector('.pvpname').textContent = path.split('/').pop();
		win.querySelector('.pvpath').textContent = 'up/' + path;
		win.querySelector('.pvinfo').textContent = '';

		// 异步获取文件信息（大小、图片尺寸）
		var infoEl = win.querySelector('.pvinfo');
		F.get('info', {path:path}, function(r){
			if (r.ok && r.info) {
				var parts = [];
				if (r.info.sizetxt) parts.push(r.info.sizetxt);
				if (r.info.width && r.info.height) parts.push(r.info.width + '×' + r.info.height);
				if (parts.length) infoEl.textContent = parts.join(' | ');
			}
		});

		// 根据类型加载内容
		var ext = path.split('.').pop().toLowerCase();
		var imgExts = {jpg:1,jpeg:1,png:1,gif:1,svg:1,webp:1,bmp:1,ico:1};
		var vidExts = {mp4:1,mkv:1,avi:1,webm:1,mov:1,wmv:1,flv:1};
		var audExts = {mp3:1,wav:1,flac:1,ogg:1,aac:1};
		var txtExts = {txt:1,html:1,css:1,js:1,json:1,xml:1,toml:1,md:1,ini:1,yaml:1,yml:1,csv:1,log:1,cfg:1,env:1,sql:1};

		var isImg = imgExts[ext];
		var body = win.querySelector('.pvbody');

		// 从图片切换到非图片：先销毁 viewer
		if (!isImg && FM.preview.viewer) FM.preview.destroyViewer();

		if (isImg) {
			// 图片：复用 viewer，不销毁 body
			if (!FM.preview.viewer) {
				body.innerHTML = '';
				FM.preview.initViewer(body);
			}
			var img = FM.preview.imgEl;
			if (!img.parentNode) body.appendChild(img);
			img.src = '';
			img.style.display = 'none';
			img.src = 'index.php?act=down&path=' + encodeURIComponent(path);
			FM.preview.viewer.update();
		} else {
			body.innerHTML = '';
			if (vidExts[ext]) {
				body.innerHTML = '<video controls src="index.php?act=down&path=' + encodeURIComponent(path) + '"></video>';
			} else if (audExts[ext]) {
				body.innerHTML = '<audio controls src="index.php?act=down&path=' + encodeURIComponent(path) + '"></audio>';
			} else if (txtExts[ext]) {
				body.innerHTML = '<div class="pvtxt">加载中...</div>';
				F.get('read', {path:path}, function(r){
					if (r.ok) {
						var txt = document.createElement('div');
						txt.className = 'pvtxt';
						txt.textContent = r.content.slice(0, 50000);
						body.innerHTML = '';
						body.appendChild(txt);
					} else {
						body.innerHTML = '<div class="pvunsupport"><i class="ico fa-solid fa-exclamation-triangle"></i> 读取失败: ' + F.escHtml(r.err||'') + '</div>';
					}
				});
			} else {
				body.innerHTML = '<div class="pvunsupport"><i class="ico fa-solid fa-file-o"></i> 暂不支持预览此文件类型</div>';
			}
		}
	};

	// ===== 5. 日志面板 =====
	FM.log = {};
	FM.log.open = function() {
		// 复用预览窗口做日志面板
		if (!FM.preview.win) FM.preview.open('');
		var win = FM.preview.win;
		win.querySelector('.pvpname').textContent = '操作日志';
		win.querySelector('.pvpath').textContent = '';
		var body = win.querySelector('.pvbody');
		body.innerHTML = '<div style="padding:16px;width:100%;height:100%;overflow:auto;background:#fff;font-size:13px;"><div style="margin-bottom:12px;font-weight:600;">操作日志</div><table style="width:100%;border-collapse:collapse;"><thead><tr style="background:#f9fafb;"><th style="padding:6px 10px;text-align:left;border-bottom:1px solid #e5e7eb;">时间</th><th style="padding:6px 10px;text-align:left;border-bottom:1px solid #e5e7eb;">用户</th><th style="padding:6px 10px;text-align:left;border-bottom:1px solid #e5e7eb;">操作</th><th style="padding:6px 10px;text-align:left;border-bottom:1px solid #e5e7eb;">路径</th><th style="padding:6px 10px;text-align:left;border-bottom:1px solid #e5e7eb;">详情</th></tr></thead><tbody id="logbody"><tr><td colspan="5" style="padding:20px;text-align:center;color:#9ca3af;">加载中...</td></tr></tbody></table></div>';
		win.style.display = 'flex';

		F.get('log', {limit:100,offset:0}, function(r){
			var tb = document.getElementById('logbody');
			if (!r.items || r.items.length === 0) { tb.innerHTML = '<tr><td colspan="5" style="padding:20px;text-align:center;color:#9ca3af;">暂无日志</td></tr>'; return; }
			var html = '';
			var actMap = {login:'登录',logout:'登出',upload:'上传',download:'下载',del:'删除',ren:'重命名',mkdir:'新建文件夹',paste:'粘贴',copy:'复制',zip:'ZIP下载'};
			for (var i = 0; i < r.items.length; i++) {
				var v = r.items[i];
				var actTxt = actMap[v.act] || v.act;
				html += '<tr style="border-bottom:1px solid #f3f4f6;"><td style="padding:6px 10px;font-size:12px;color:#6b7280;">' + F.escHtml(v.time) + '</td><td style="padding:6px 10px;">' + F.escHtml(v.user) + '</td><td style="padding:6px 10px;">' + F.escHtml(actTxt) + '</td><td style="padding:6px 10px;font-size:12px;color:#6b7280;">' + F.escHtml(v.path) + '</td><td style="padding:6px 10px;font-size:12px;color:#6b7280;">' + F.escHtml(v.detail) + '</td></tr>';
			}
			tb.innerHTML = html;
		});
	};

	// ===== 6. 退出 =====
	FM.logout = function() {
		window.location.href = 'login.php';
	};

	// ===== 7. 初始化 =====
	FM.init = function() {
		// 读取保存的视图偏好
		var savedView = localStorage.getItem('fm_view');
		if (savedView) FM.mgr.view = savedView;

		// 根据 URL hash 读取初始路径
		var hash = location.hash.replace(/^#/, '');
		var initPath = '';
		if (hash) {
			try { initPath = decodeURIComponent(hash); } catch(e) { initPath = hash; }
		}
		// 背景窗口
		var bg = FM.win.create(initPath, FM.mgr.view);

		// 新建窗口按钮
		document.querySelector('.bnewwin').onclick = function(){
			var p = FM.mgr.wins.length > 0 ? FM.mgr.wins[FM.mgr.wins.length-1].path : '';
			FM.win.create(p, FM.mgr.view);
		};

		// 系统菜单
		var sysBtn = document.querySelector('.btnsys');
		var sysDrop = document.querySelector('.sysdrop');
		if (sysBtn && sysDrop) {
			sysBtn.onclick = function(e){
				e.stopPropagation();
				sysDrop.classList.toggle('hide');
			};
			document.addEventListener('click', function(){ sysDrop.classList.add('hide'); }, false);
			sysDrop.onclick = function(e){
				e.stopPropagation();
				var item = e.target.closest('.mitem');
				if (!item) return;
				var act = item.getAttribute('data-act');
				sysDrop.classList.add('hide');
				if (act === 'log') { if (FM.log && FM.log.open) FM.log.open(); }
				else if (act === 'uplist') { FM.upload.showPanel(); }
			};
		}

		document.querySelector('.bexit').onclick = FM.logout;

		// 文件选择上传
		document.getElementById('fileinput').onchange = function() {
			var dir = this.getAttribute('data-dir') || '';
			for (var i = 0; i < this.files.length; i++) {
				FM.upload.addTask(this.files[i], dir);
			}
			this.value = '';
		};

		// 拖拽上传（全局）
		document.querySelector('.winarea').ondragover = function(e) { e.preventDefault(); };
		document.querySelector('.winarea').ondrop = function(e) {
			e.preventDefault();
			var files = e.dataTransfer.files;
			if (files.length === 0) return;
			// 找到最近的窗口
			var rects = FM.mgr.wins.map(function(w){ var r = w.el.getBoundingClientRect(); return {win:w, rect:r}; });
			var target = FM.mgr.wins[0];
			for (var i = rects.length-1; i >= 0; i--) {
				var r = rects[i].rect;
				if (e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom) {
					target = rects[i].win; break;
				}
			}
			for (var j = 0; j < files.length; j++) {
				if (files[j].type || files[j].size > 0) FM.upload.addTask(files[j], target.path);
			}
		};

		// 键盘快捷键
		document.addEventListener('keydown', function(e){
			// 输入框中不处理快捷键
			if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
			var ctrl = e.ctrlKey || e.metaKey;
			// 获取当前活动窗口
			var cur = FM.mgr.wins[FM.mgr.wins.length-1];
			if (!cur) return;

			if (ctrl && e.key === 'a') {
				e.preventDefault();
				cur.sel = cur.items.map(function(v){ return v.name; });
				FM.win.updateSel(cur);
			} else if (ctrl && e.key === 'c') {
				if (cur.sel.length) {
					var paths = cur.sel.map(function(n){ return cur.path ? cur.path+'/'+n : n; });
					FM.mgr.clip = {type:'copy', paths:paths};
					FM.updateClipBtns();
					F.toast('已复制 ' + cur.sel.length + ' 项');
				}
			} else if (ctrl && e.key === 'x') {
				if (cur.sel.length) {
					var paths = cur.sel.map(function(n){ return cur.path ? cur.path+'/'+n : n; });
					FM.mgr.clip = {type:'cut', paths:paths};
					FM.updateClipBtns();
					F.toast('已剪切 ' + cur.sel.length + ' 项');
				}
			} else if (ctrl && e.key === 'v') {
				FM.ctx.exec('paste', {_win:cur, _path:cur.path});
			} else if (e.key === 'Delete') {
				if (cur.sel.length) {
					var ps = cur.sel.map(function(n){ return cur.path ? cur.path+'/'+n : n; });
					F.confirm('确定要删除选中的 ' + ps.length + ' 项吗？', function(){
						var done = 0;
						(function next(i){
							if (i >= ps.length) { F.toast('已删除 ' + done + ' 项', 'ok'); FM.win.load(cur, cur.path); return; }
							F.post('del', {path:ps[i]}, function(r){ if(r.ok) done++; next(i+1); });
						})(0);
					});
				}
			} else if (e.key === 'F2') {
				e.preventDefault();
				if (cur.sel.length === 1) {
					var p = cur.sel[0];
					var item = FM.getItem(cur, p);
					if (item) {
						F.prompt('重命名', item.name, function(v){
							if (v && v !== item.name) {
								var full = cur.path ? cur.path + '/' + p : p;
								F.post('ren', {path:full, name:v}, function(r){
									if (r.ok) { F.toast('已重命名', 'ok'); FM.win.load(cur, cur.path); }
									else F.toast(r.err||'重命名失败', 'err');
								});
							}
						});
					}
				} else if (cur.sel.length > 1) {
					F.toast('暂不支持批量重命名', 'err');
				}
			}
		});

		// 文件区点击取消选中（点击空白区/背景时取消）
		document.addEventListener('mousedown', function(e){
			var el = e.target;
			// 向上查找是否点击在窗口文件区内
			while (el) {
				if (el.classList && (el.classList.contains('filelist') || el.classList.contains('filearea') || el.classList.contains('emptystate'))) {
					var win = null;
					for (var i = 0; i < FM.mgr.wins.length; i++) {
						if (FM.mgr.wins[i].el.contains(e.target)) { win = FM.mgr.wins[i]; break; }
					}
					// 只在点击空白区（文件项之外）时取消选中
					if ((el.classList.contains('emptystate') || el.classList.contains('filearea')) && !e.target.closest('.fitem')&&!e.target.closest('.frow')&&!e.target.closest('.filehead')) {
						if (win) { win.sel = []; FM.win.updateSel(win); }
					}
					// 点击 filelist 空白间距区域
					if (el.classList.contains('filelist') && !e.target.closest('.fitem')&&!e.target.closest('.frow')) {
						if (win) { win.sel = []; FM.win.updateSel(win); }
					}
					break;
				}
				el = el.parentNode;
				if (!el || el === document.body) break;
			}
		});

		// 文件夹背景区域右键菜单
		document.addEventListener('contextmenu', function(e){
			// 找到所在的窗口
			var win = null;
			for (var i = 0; i < FM.mgr.wins.length; i++) {
				if (FM.mgr.wins[i].el.contains(e.target)) { win = FM.mgr.wins[i]; break; }
			}
			if (!win) return;
			// 检查是否点击在文件背景上（不是文件项、不是列头）
			if (e.target.closest('.fitem') || e.target.closest('.frow') || e.target.closest('.filehead')) return;
			// 必须是 filelist/filearea 或其子元素
			var bg = e.target.closest('.filelist, .filearea');
			if (!bg) return;
			e.preventDefault();
			win.sel = [];
			FM.win.updateSel(win);
			FM.ctx.show(e, win, win.path ? win.path : '', null);
		});

		// 所有视图：鼠标框选
		document.addEventListener('mousedown', function(e){
			var el = e.target;
			// 找到窗口
			var win = null;
			for (var i = 0; i < FM.mgr.wins.length; i++) {
				if (FM.mgr.wins[i].el.contains(e.target)) { win = FM.mgr.wins[i]; break; }
			}
			if (!win) return;
			var list = win.el.querySelector('.filelist');
			if (!list) return;
			// 右键或中键不框选
			if (e.button !== 0) return;
			// 点击在列头不框选
			if (e.target.closest('.filehead')) return;
			// 点击在文件项上（图标fitem、详细视图frow）不框选
			if (e.target.closest('.fitem') || e.target.closest('.frow')) return;
			// 只有点击空白区域（filelist/filearea 背景）才启用框选
			var isListBg = (e.target === list || el.classList.contains('filelist') || el.classList.contains('filearea'));
			if (!isListBg) return;

			e.preventDefault();
			var startX = e.clientX;
			var startY = e.clientY;
			var box = document.createElement('div');
			box.className = 'rubberband';
			document.body.appendChild(box);

			function onMove(ev) {
				var x = Math.min(ev.clientX, startX);
				var y = Math.min(ev.clientY, startY);
				var w = Math.abs(ev.clientX - startX);
				var h = Math.abs(ev.clientY - startY);
				box.style.left = x + 'px';
				box.style.top = y + 'px';
				box.style.width = w + 'px';
				box.style.height = h + 'px';

				var rLeft = Math.min(startX, ev.clientX), rTop = Math.min(startY, ev.clientY);
				var rRight = Math.max(startX, ev.clientX), rBottom = Math.max(startY, ev.clientY);
				win.sel = [];
				list.querySelectorAll('.fitem, .frow').forEach(function(it){
					var r = it.getBoundingClientRect();
					if (r.left < rRight && r.right > rLeft && r.top < rBottom && r.bottom > rTop) {
						win.sel.push(it.getAttribute('data-path'));
					}
				});
				// 更新样式
				list.querySelectorAll('.fitem, .frow').forEach(function(it){
					var p = it.getAttribute('data-path');
					if (win.sel.indexOf(p) !== -1) it.classList.add('sel');
					else it.classList.remove('sel');
				});
			}
			function onUp() {
				box.parentNode.removeChild(box);
				document.removeEventListener('mousemove', onMove);
				document.removeEventListener('mouseup', onUp);
			}
			document.addEventListener('mousemove', onMove);
			document.addEventListener('mouseup', onUp);
		});
	};

	// 页面加载后初始化
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', FM.init);
	} else {
		FM.init();
	}

	window.FM = FM;
})();