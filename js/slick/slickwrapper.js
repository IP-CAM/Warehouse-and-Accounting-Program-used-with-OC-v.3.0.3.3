/*global App*/

(function($){
    function SlickWrapper(node,settings){
	var grid;
	var loader;
	var loadingIndicator = null;
	var searchInput=null;
	var searchClock;
	var data = settings.data;
	var columns=settings.columns;
	var options=settings.options;

	function init(){
	    loader = new Slick.Data.RemoteModel();
	    grid=new Slick.Grid(node, loader.data, columns, options);
	    grid.setSelectionModel(new Slick.RowSelectionModel());
	    if( options.enableSearch ){
		initSearch();
	    }
	    initLoader();
	};
	var urls=[
	    "js/slick/lib/jquery.event.drag-2.3.0.js",
	    "js/slick/slick.core.js",
	    "js/slick/slick.grid.js",
	    "js/slick/plugins/slick.rowselectionmodel.js",
	    'js/slick/lib/jquery.jsonp-2.4.min.js'];
	App.require(urls,init);
	function initSearch(){
	    node.prepend('<input style="width:100%" placeholder="&#128269; Поиск в таблице..." class="slick-searchinput"/>');
	    searchInput=node.find('input');
	    searchInput.keyup(function (e) {
		if (e.which === 13) {
		    startsearch();
		} else {
		    clearTimeout(searchClock);
		    searchClock=setTimeout(startsearch,500);
		}
	    });
	    function startsearch(){
		loader.setSearch(searchInput.val());
		var vp = grid.getViewport();
		loader.ensureData(vp.top, vp.bottom);
	    }
	}
	function initLoader(){
	    grid.onViewportChanged.subscribe(function (e, args) {
		var vp = grid.getViewport();
		loader.ensureData(vp.top, vp.bottom);
	    });
	    grid.onSort.subscribe(function (e, args) {
		loader.setSort(args.sortCol.field, args.sortAsc ? 1 : -1);
		var vp = grid.getViewport();
		loader.ensureData(vp.top, vp.bottom);
	    });
	    loader.onDataLoading.subscribe(function () {
		if (!loadingIndicator) {
		    loadingIndicator = $("<span class='loading-indicator'><label>Buffering...</label></span>").appendTo(document.body);
		    var $g = node;
		    loadingIndicator
			    .css("position", "absolute")
			    .css("top", $g.position().top + $g.height() / 2 - loadingIndicator.height() / 2)
			    .css("left", $g.position().left + $g.width() / 2 - loadingIndicator.width() / 2);
		}
		loadingIndicator.show();
	    });
	    loader.onDataLoaded.subscribe(function (e, args) {
		for (var i = args.from; i <= args.to; i++) {
		    grid.invalidateRow(i);
		}
		grid.updateRowCount();
		grid.render();
		loadingIndicator.fadeOut();
	    });
	    loader.setSearch($("#txtSearch").val());
	    loader.setSort("score", -1);
	    grid.setSortColumn("score", false);
	    grid.onViewportChanged.notify();
	};
	
	return this;
    };
    window.SlickWrapper=SlickWrapper;
})(jQuery);
$.fn.slickgrid=function(settings){
    return new SlickWrapper(this,settings);
};















(function ($) {
  /***
   * A sample AJAX data store implementation.
   * Right now, it's hooked up to load search results from Octopart, but can
   * easily be extended to support any JSONP-compatible backend that accepts paging parameters.
   */
  function RemoteModel() {
    // private
    var PAGESIZE = 50;
    var data = {length: 0};
    var searchstr = "";
    var sortcol = null;
    var sortdir = 1;
    var h_request = null;
    var req = null; // ajax request

    // events
    var onDataLoading = new Slick.Event();
    var onDataLoaded = new Slick.Event();


    function init() {
    }


    function isDataLoaded(from, to) {
      for (var i = from; i <= to; i++) {
        if (data[i] == undefined || data[i] == null) {
          return false;
        }
      }

      return true;
    }


    function clear() {
      for (var key in data) {
        delete data[key];
      }
      data.length = 0;
    }


    function ensureData(from, to) {
      if (req) {
        req.abort();
        for (var i = req.fromPage; i <= req.toPage; i++)
          data[i * PAGESIZE] = undefined;
      }

      if (from < 0) {
        from = 0;
      }

      if (data.length > 0) {
        to = Math.min(to, data.length - 1);
      }

      var fromPage = Math.floor(from / PAGESIZE);
      var toPage = Math.floor(to / PAGESIZE);

      while (data[fromPage * PAGESIZE] !== undefined && fromPage < toPage)
        fromPage++;

      while (data[toPage * PAGESIZE] !== undefined && fromPage < toPage)
        toPage--;

      if (fromPage > toPage || ((fromPage == toPage) && data[fromPage * PAGESIZE] !== undefined)) {
        // TODO:  look-ahead
        onDataLoaded.notify({from: from, to: to});
        return;
      }

      var url = "http://octopart.com/api/v3/parts/search?apikey=68b25f31&include[]=short_description&show[]=uid&show[]=manufacturer&show[]=mpn&show[]=brand&show[]=octopart_url&show[]=short_description&q=" + searchstr + "&start=" + (fromPage * PAGESIZE) + "&limit=" + (((toPage - fromPage) * PAGESIZE) + PAGESIZE);

      if (sortcol != null) {
        url += ("&sortby=" + sortcol + ((sortdir > 0) ? "+asc" : "+desc"));
      }

      if (h_request != null) {
        clearTimeout(h_request);
      }

      h_request = setTimeout(function () {
        for (var i = fromPage; i <= toPage; i++)
          data[i * PAGESIZE] = null; // null indicates a 'requested but not available yet'

        onDataLoading.notify({from: from, to: to});

        req = $.jsonp({
          url: url,
          callbackParameter: "callback",
          cache: true,
          success: onSuccess,
          error: function () {
            onError(fromPage, toPage)
          }
        });
        req.fromPage = fromPage;
        req.toPage = toPage;
      }, 50);
    }


    function onError(fromPage, toPage) {
      alert("error loading pages " + fromPage + " to " + toPage);
    }

    function onSuccess(resp) {
      var from = resp.request.start, to = from + resp.results.length;
      data.length = Math.min(parseInt(resp.hits),1000); // limitation of the API

      for (var i = 0; i < resp.results.length; i++) {
        var item = resp.results[i].item;

        data[from + i] = item;
        data[from + i].index = from + i;
      }

      req = null;

      onDataLoaded.notify({from: from, to: to});
    }


    function reloadData(from, to) {
      for (var i = from; i <= to; i++)
        delete data[i];

      ensureData(from, to);
    }


    function setSort(column, dir) {
      sortcol = column;
      sortdir = dir;
      clear();
    }

    function setSearch(str) {
      searchstr = str;
      clear();
    }


    init();

    return {
      // properties
      "data": data,

      // methods
      "clear": clear,
      "isDataLoaded": isDataLoaded,
      "ensureData": ensureData,
      "reloadData": reloadData,
      "setSort": setSort,
      "setSearch": setSearch,

      // events
      "onDataLoading": onDataLoading,
      "onDataLoaded": onDataLoaded
    };
  }

  // Slick.Data.RemoteModel
  $.extend(true, window, { Slick: { Data: { RemoteModel: RemoteModel }}});
})(jQuery);