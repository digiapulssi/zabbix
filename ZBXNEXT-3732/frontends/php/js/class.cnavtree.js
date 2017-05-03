jQuery(function($) {
	/**
	 * Create Navigation Tree element.
	 *
	 * @return object
	 */

	if (typeof($.fn.zbx_navtree) === 'undefined') {
		$.fn.zbx_navtree = function(input) {
			var $this = $(this),
					widgetid,
					editMode,
					lastId = 0,
					root;

			var buttonCssAdd = {
				'background': "url('data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+Cjxzdmcgd2lkdGg9IjIwcHgiIGhlaWdodD0iMjBweCIgdmlld0JveD0iMCAwIDIwIDIwIiB2ZXJzaW9uPSIxLjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiPgogICAgPCEtLSBHZW5lcmF0b3I6IFNrZXRjaCAzLjguMyAoMjk4MDIpIC0gaHR0cDovL3d3dy5ib2hlbWlhbmNvZGluZy5jb20vc2tldGNoIC0tPgogICAgPHRpdGxlPjIweDIwL1BsdXM8L3RpdGxlPgogICAgPGRlc2M+Q3JlYXRlZCB3aXRoIFNrZXRjaC48L2Rlc2M+CiAgICA8ZGVmcz48L2RlZnM+CiAgICA8ZyBpZD0iMjB4MjAiIHN0cm9rZT0ibm9uZSIgc3Ryb2tlLXdpZHRoPSIxIiBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCI+CiAgICAgICAgPGcgaWQ9IjIweDIwL1BsdXMiIHN0cm9rZT0iIzM2NDM0RCI+CiAgICAgICAgICAgIDxnIGlkPSJQbHVzIj4KICAgICAgICAgICAgICAgIDxnIGlkPSJJY29uIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgyLjAwMDAwMCwgMi4wMDAwMDApIj4KICAgICAgICAgICAgICAgICAgICA8cGF0aCBkPSJNMCw4IEwxNiw4IiBpZD0iTGluZS00Ij48L3BhdGg+CiAgICAgICAgICAgICAgICAgICAgPHBhdGggZD0iTTgsMCBMOCwxNiIgaWQ9IkxpbmUtMyI+PC9wYXRoPgogICAgICAgICAgICAgICAgPC9nPgogICAgICAgICAgICA8L2c+CiAgICAgICAgPC9nPgogICAgPC9nPgo8L3N2Zz4=') no-repeat left center",
				'background-size': 'cover',
				'border': '0px none',
				'cursor': 'pointer',
				'height': '15px',
				'width': '14px'
			};
			var buttonCssEdit = {
				'background': "url('data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+Cjxzdmcgd2lkdGg9IjIwcHgiIGhlaWdodD0iMjBweCIgdmlld0JveD0iMCAwIDIwIDIwIiB2ZXJzaW9uPSIxLjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiPgogICAgPCEtLSBHZW5lcmF0b3I6IFNrZXRjaCAzLjguMyAoMjk4MDIpIC0gaHR0cDovL3d3dy5ib2hlbWlhbmNvZGluZy5jb20vc2tldGNoIC0tPgogICAgPHRpdGxlPjIweDIwL0VkaXQ8L3RpdGxlPgogICAgPGRlc2M+Q3JlYXRlZCB3aXRoIFNrZXRjaC48L2Rlc2M+CiAgICA8ZGVmcz4KICAgICAgICA8cGF0aCBkPSJNMTIuODk0ODI2LDEuNTY5NjQwMDUgQzEzLjY3MjcwNTIsMC43OTE3NjA4OSAxNC45MzUzODM1LDAuNzkzMjQ3ODQ0IDE1LjcyMDk2NjIsMS41Nzg4MzA1NCBMMTYuNDIxMTY5NSwyLjI3OTAzMzg0IEMxNy4yMDQxMjQ0LDMuMDYxOTg4NzYgMTcuMjA3MTA3NSw0LjMyODQyNjQ1IDE2LjQzMDM1OTksNS4xMDUxNzM5NiBMNy4yMzIyMzMwNSwxNC4zMDMzMDA5IEwyLjg3NzA1NjEsMTUuNzU1MDI2NSBDMi4zNTM0MjE3MiwxNS45Mjk1NzEzIDIuMDY3NjQ1OSwxNS42NTQ5MjY3IDIuMjQ0OTczNDksMTUuMTIyOTQzOSBMMy42OTY2OTkxNCwxMC43Njc3NjcgTDEyLjg5NDgyNiwxLjU2OTY0MDA1IEwxMi44OTQ4MjYsMS41Njk2NDAwNSBaIiBpZD0icGF0aC0xIj48L3BhdGg+CiAgICAgICAgPG1hc2sgaWQ9Im1hc2stMiIgbWFza0NvbnRlbnRVbml0cz0idXNlclNwYWNlT25Vc2UiIG1hc2tVbml0cz0ib2JqZWN0Qm91bmRpbmdCb3giIHg9IjAiIHk9IjAiIHdpZHRoPSIxNC44MTgzMTY4IiBoZWlnaHQ9IjE0LjgxOTQ2NyIgZmlsbD0id2hpdGUiPgogICAgICAgICAgICA8dXNlIHhsaW5rOmhyZWY9IiNwYXRoLTEiPjwvdXNlPgogICAgICAgIDwvbWFzaz4KICAgIDwvZGVmcz4KICAgIDxnIGlkPSIyMHgyMCIgc3Ryb2tlPSJub25lIiBzdHJva2Utd2lkdGg9IjEiIGZpbGw9Im5vbmUiIGZpbGwtcnVsZT0iZXZlbm9kZCI+CiAgICAgICAgPGcgaWQ9IjIweDIwL0VkaXQiPgogICAgICAgICAgICA8ZyBpZD0iRWRpdCI+CiAgICAgICAgICAgICAgICA8ZyBpZD0iSWNvbiIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMi4wMDAwMDAsIDAuMDAwMDAwKSI+CiAgICAgICAgICAgICAgICAgICAgPHBhdGggZD0iTTAsMTguNSBMMTYsMTguNSIgaWQ9IkxpbmUtNDIiIHN0cm9rZT0iIzM2NDM0RCIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48L3BhdGg+CiAgICAgICAgICAgICAgICAgICAgPHVzZSBpZD0iTGluZS00MSIgc3Ryb2tlPSIjMzU0MjRDIiBtYXNrPSJ1cmwoI21hc2stMikiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InNxdWFyZSIgeGxpbms6aHJlZj0iI3BhdGgtMSI+PC91c2U+CiAgICAgICAgICAgICAgICAgICAgPHBhdGggZD0iTTExLjg4OTA4NzMsNS4xMTA5MTI3IEwxMy44ODkwODczLDUuMTEwOTEyNyIgaWQ9IkxpbmUtNDAiIHN0cm9rZT0iIzM1NDI0QyIgc3Ryb2tlLWxpbmVjYXA9InNxdWFyZSIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMTIuODg5MDg3LCA1LjExMDkxMykgcm90YXRlKC0zMTUuMDAwMDAwKSB0cmFuc2xhdGUoLTEyLjg4OTA4NywgLTUuMTEwOTEzKSAiPjwvcGF0aD4KICAgICAgICAgICAgICAgICAgICA8cGF0aCBkPSJNNC44MTgwMTk0OCwxMi4xODE5ODA1IEw2LjgxODAxOTQ4LDEyLjE4MTk4MDUiIGlkPSJMaW5lLTM5IiBzdHJva2U9IiMzNTQyNEMiIHN0cm9rZS1saW5lY2FwPSJzcXVhcmUiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDUuODE4MDE5LCAxMi4xODE5ODEpIHJvdGF0ZSgtMzE1LjAwMDAwMCkgdHJhbnNsYXRlKC01LjgxODAxOSwgLTEyLjE4MTk4MSkgIj48L3BhdGg+CiAgICAgICAgICAgICAgICA8L2c+CiAgICAgICAgICAgIDwvZz4KICAgICAgICA8L2c+CiAgICA8L2c+Cjwvc3ZnPg==')",
				'background-size': 'cover',
				'border': '0px none',
				'cursor': 'pointer',
				'height': '15px',
				'width': '14px'
			};
			var buttonCssRemove = {
				'background': "url('data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+Cjxzdmcgd2lkdGg9IjIwcHgiIGhlaWdodD0iMjBweCIgdmlld0JveD0iMCAwIDIwIDIwIiB2ZXJzaW9uPSIxLjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiPgogICAgPCEtLSBHZW5lcmF0b3I6IFNrZXRjaCAzLjguMyAoMjk4MDIpIC0gaHR0cDovL3d3dy5ib2hlbWlhbmNvZGluZy5jb20vc2tldGNoIC0tPgogICAgPHRpdGxlPjIweDIwL0Nyb3NzPC90aXRsZT4KICAgIDxkZXNjPkNyZWF0ZWQgd2l0aCBTa2V0Y2guPC9kZXNjPgogICAgPGRlZnM+PC9kZWZzPgogICAgPGcgaWQ9IjIweDIwIiBzdHJva2U9Im5vbmUiIHN0cm9rZS13aWR0aD0iMSIgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPgogICAgICAgIDxnIGlkPSIyMHgyMC9Dcm9zcyIgc3Ryb2tlPSIjMzY0MzREIj4KICAgICAgICAgICAgPGcgaWQ9IkNyb3NzIj4KICAgICAgICAgICAgICAgIDxnIGlkPSJJY29uIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgyLjAwMDAwMCwgMi4wMDAwMDApIj4KICAgICAgICAgICAgICAgICAgICA8cGF0aCBkPSJNLTEuMzg1NDk3ODMsOCBMMTcuMzg1NDk3OCw4IiBpZD0iTGluZS0yIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSg4LjAwMDAwMCwgOC4wMDAwMDApIHJvdGF0ZSgtMzE1LjAwMDAwMCkgdHJhbnNsYXRlKC04LjAwMDAwMCwgLTguMDAwMDAwKSAiPjwvcGF0aD4KICAgICAgICAgICAgICAgICAgICA8cGF0aCBkPSJNOCwtMS4zODU0OTc4MyBMOCwxNy4zODU0OTc4IiBpZD0iTGluZS0xIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSg4LjAwMDAwMCwgOC4wMDAwMDApIHJvdGF0ZSgtMzE1LjAwMDAwMCkgdHJhbnNsYXRlKC04LjAwMDAwMCwgLTguMDAwMDAwKSAiPjwvcGF0aD4KICAgICAgICAgICAgICAgIDwvZz4KICAgICAgICAgICAgPC9nPgogICAgICAgIDwvZz4KICAgIDwvZz4KPC9zdmc+')",
				'background-size': 'cover',
				'cursor': 'pointer',
				'border': '0px none',
				'height': '15px',
				'width': '14px'
			};

			var drawTree = function() {
				root = createTreeBranch(),
				data = $this.data('treeData');

				if (data.treeData) {
					for (var id in data.treeData){
						if(typeof data.treeData[id] != 'function'){
							root.append(createTreeLeap(id, data.treeData[id]));
							if(id > lastId){
								lastId = id;
							}
						}
					}
				}
				$this.append(root);
			};
			
			var parseProblems = function() {
				var colors = {0:'#97AAB3', 1:'#7499FF', 2:'#FFC859', 3:'#FFA059', 4:'#E97659', 5: '#E45959'};
				data = $this.data('treeData');

				if(data.problems.length){
					for(var map in data.problems){
						data.problems[map].each(function(numb,sev){
							if (!numb) return;
							$('.tree-item[data-mapid='+map+']').attr('data-problems'+sev, numb);
						});
					}
				}

				$('.tree-item', $this).each(function(){
					var id = $(this).data('id'),
							obj = $(this);
					
					for(var sev=0; 5>=sev; sev++){
						var sum = 0;
						if (typeof obj.data('problems'+sev) != 'undefined'){
							sum += +obj.data('problems'+sev);
						}
						$('[data-problems'+sev+']', obj).each(function(){
							sum += +$(this).data('problems'+sev);
						});
						if(sum){
							obj.attr('data-problems'+sev, sum);
						}
					}
				});

				for(var i=0; 5>=i; i++){
					$('[data-problems'+i+']', $this).each(function(){
						var id = $(this).data('id'),
								obj = $(this);

						var notif = $('<span></span>')
							.html(obj.attr('data-problems'+i))
							.css({
								'background':colors[i],
								'color':'#ffffff',
								'padding':'3px 4px 2px',
								'font-size':'12px',
								'line-height':'12px',
								'border-radius':'3px',
								'margin':'0px 5px 0px 0px',
								'float':'right'
							});

						$('[data-id='+id+']>.row', $this).append(notif);
					});
				}
			};

			var createTreeBranch = function() {
				var ul = $('<ul></ul>');
				return ul;
			};

			var createTreeLeap = function(id, item) {
				var a, span, li, ul,
						data = $this.data('treeData');

				a = $('<a></a>')
					.click(function(e) {
						e.preventDefault();
						if (!editMode) {
							var mapid = $(this).data('mapid');
							$(".dashbrd-grid-widget-container")
								.dashboardGrid("widgetDataShare", {widgetid: data.widgetid}, {mapid: mapid});
						}
					})
					.addClass('item-name')
					.attr({'href': '#', 'data-mapid': item.mapid})
					.text(item.name);

				span = $('<span></span>').addClass('row').append(a);
				li = $('<li></li>')
								.attr({'data-mapid': item.mapid})
								.addClass('tree-item opened')
								.append(span);
				ul = createTreeBranch();

				if (typeof(item.children) !== 'undefined') {
					$('<span></span>').insertBefore(a).addClass('arrow-down').click(function(e) {
						var branch = $(this).closest('[data-id]');
						if (branch.hasClass('opened')) {
							$(this).addClass('arrow-right').removeClass('arrow-down');
							branch.removeClass('opened').addClass('closed');
						} else {
							$(this).addClass('arrow-down').removeClass('arrow-right');
							branch.removeClass('closed').addClass('opened');
						}
					});

					for (var cid in item.children) {
						ul.append(createTreeLeap(cid, item.children[cid]));
						if(cid > lastId){
							lastId = cid;
						}
					}
				}

				if (editMode) {
					var tools = $('<div></div>').addClass('tools').insertAfter(a);

					$('<input>')
						.click(function(){
							var parentId = $(this).data('id'),
									branch = $('.tree-item[data-id='+parentId+']>ul', $this),
									newItem = {name: 'New item', parent: parentId};

							if(!branch.size()){
								branch = createTreeBranch();
								branch.appentTo($('.tree-item[data-id='+parentId+']', $this));
							}

							lastId++;
							newItem.name;
							branch.append(createTreeLeap(lastId, newItem));
						})
						.addClass('add-child-btn')
						.attr({'type':'button', 'data-id':id})
						.css(buttonCssAdd)
						.appendTo(tools);

					$('<input>')
						.click(function(){
							var id = $(this).data('id');
							var url = new Curl('zabbix.php');
							
							var ajax_data = {
								map_name: $('[name="map.name.'+id+'"]', $this).val(),
								mapid: $('[name="mapid.'+id+'"]', $this).val(),
								map_id: id
							};

							url.setArgument('action', 'widget.navigationtree.edititem');

							jQuery.ajax({
								url: url.getUrl(),
								method: 'POST',
								data: ajax_data,
								dataType: 'json',
								success: function(resp) {
									overlayDialogue({
										'title': t('Edit Tree Widget item'),
										'content': resp.body,
										'buttons': [
											{
												'title': t('Update'),
												'class': 'dialogue-widget-save',
												'action': function() {
													var id = ajax_data.map_id,
															form = $('#widget_dialogue_form'),
															name = $('[name="map.name.'+id+'"]', form).val(),
															map = $('[name="linked_map_id"]', form).val();

													$('[name="map.name.'+id+'"]', $this).val(name);
													$('[name="mapid.'+id+'"]', $this).val(map);
													$('[data-id='+id+'] > .row > .item-name', $this).html(name);
												}
											},
											{
												'title': t('Cancel'),
												'class': 'btn-alt',
												'action': function() {}
											}
										]
									});
								}
							});
						})
						.addClass('edit-item-btn')
						.attr({'type':'button', 'data-id':id})
						.css(buttonCssEdit)
						.appendTo(tools);

					$('<input>')
						.click(function(){
							methods.removeItem.apply($this, [$(this).data('id')]);
						})
						.attr({'type':'button', 'data-id':id})
						.addClass('remove-item-btn')
						.css(buttonCssRemove)
						.appendTo(tools);

					$('<input>')
						.attr({'type':'hidden', 'name':'map.name.'+id})
						.val(item.name)
						.appendTo(li);

					$('<input>')
						.attr({'type':'hidden', 'name':'map.parent.'+id})
						.val(item.parent)
						.appendTo(li);

					$('<input>')
						.attr({'type':'hidden', 'name':'mapid.'+id})
						.val(item.mapid)
						.appendTo(li);
				}

				li.attr({'data-id':id}).append(ul);
				return li;
			};

			var initUI = function() {
				if (!editMode) return false;
				
				destroyUI();
				var toolBar = $('<div></div>')
					.appendTo($this.closest('.navtree'))
					.addClass('buttons');

				$('<button></button>')
					.click(function(){
						lastId++;
						root.append(createTreeLeap(lastId, {name: 'New item'}));
					})
					.appendTo(toolBar)
					.html('Add');
				/*
				$('<button></button>')
					.click(function(){
						alert('import maps');
					})
					.appendTo(toolBar)
					.html('Import maps');
					*/
			};

			var destroyUI = function() {
				$('.buttons', $this.closest('.navtree')).remove();
			};

			var init = function(options) {
				editMode = options.edit||false,

				$this.data('treeData', {
					widgetid: options.widgetid,
					treeData: options.tree||[],
					problems: options.problems||[]
				});

				if ($this.length === 0) {
					return false;
				}

				drawTree();
				parseProblems();
				initUI();
			};
			
			var methods = {
				removeItem: function(id){
					if(confirm('Remove item and all its children?')){
						$('[data-id='+id+']').remove();
					}
				},
				onEditStart: function(){
					methods.switchToEditMode();
				},
				onEditStop: function(){
					methods.switchToNavigationMode();
				},
				beforeSave: function(){
					// TODO miks: here the values of editable field should be collected to be sent for saving.
					//alert('this is called before saving dashboard.');
				},
				switchToEditMode: function(){
					// TODO miks: cancel refresh
					destroyUI();
					$this.empty();
					editMode = true;
					drawTree();
					initUI();
				},
				switchToNavigationMode: function(){
					// TODO miks: switch refresh on // startWidgetRefresh
					destroyUI();
					$this.empty();
					editMode = false;
					drawTree();
					parseProblems();
				}
			};

			if (methods[input]) {
				return methods[input].apply(this, Array.prototype.slice.call(arguments, 1));
			} else if (typeof input === 'object') {
				return init.apply(this, arguments);
			} else {
				return null;
			}
		}
	}
});


