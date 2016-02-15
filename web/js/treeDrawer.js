/**
 * Stage
 * @type Kinetic.Stage
 */
var stage = null;

/**
 * Layer where to add the elements
 * @type Kinetic.Layer
 */
var layer = null;

/**
 * Store line IDs
 * @type Array
 */
var lines = new Array();

var tables = new Array();

function loadGraph() {
    $('.tableList').html('');
    $('.tableList').show();
    if (stage == null) {
        stage = new Kinetic.Stage({
            container: 'canvas',
            width: $(window).width(),
            height: $(window).height()
        });

        /**
         * Layer where to add the elements
         * @type Kinetic.Layer
         */
        layer = new Kinetic.Layer();
    }
    if ($('#sortableFinal').find('li').length > 0) {
        var ids = new Array();
        $('#sortableFinal').find('li').each(function() {
            ids.push($(this).attr('id'));
        });

        $.post('/PgExplorer/web/app_dev.php/graphic/getTree',
            {ids:ids.join(';'), linkedTables:$('#linkedTables').val()}, 
            function(data) {
                $('.tableList').html(data);
                layer.removeChildren();
                layer.clear();
                stage.remove(layer);
                stage.clear();
                drawTables();
            });
    } else {
        alert('No table selected.');
    }

}

function drawTables() {
    var rotationAngle = Math.round(360 / $('.tbl-satellite').length);
    var defaultRadius = 400;
    var satelliteX = 0;
    var satelliteY = 0;
    $('.tbl-origin').each(function(indexOrigin) {
        var idFrom = $(this).attr('id');

        var initX = Math.round(stage.getWidth() / 2 + indexOrigin * defaultRadius * 2 + $('#' + idFrom + ' table:first').width() / 2);
        var initY = Math.round(stage.getHeight() / 2 + indexOrigin * defaultRadius * 2 + $('#' + idFrom + ' table:first').height());
        if (tables.indexOf(idFrom) == -1) {
            addTable(idFrom, initX, initY);
            tables.push(idFrom);
        }
        //Satellites handling
        var indexSat = 0;

        $(this).nextAll('.tbl-satellite').each(function() {

            var id = $(this).attr('id');

            if (tables.indexOf(id) == -1) {
                satelliteX = Math.abs(Math.round(initX + Math.cos(indexSat * rotationAngle) * defaultRadius));
                satelliteY = Math.abs(Math.round(initY + Math.sin(indexSat * rotationAngle) * defaultRadius));

                //console.log(id + '-' + [satelliteX, satelliteY]);
                addTable(id, satelliteX, satelliteY);

                tables.push(id);
                arrow(idFrom + '-' + id, initX, initY, satelliteX, satelliteY);
                indexSat++;
            }

        });
    });

    stage.setHeight(satelliteY + defaultRadius);
    stage.setWidth(satelliteX + defaultRadius);
    stage.add(layer);
    $('.tbl-origin,.tbl-satellite').hide();
}

/**
 * Add table from HTML element to the kinetic layer
 * @type @exp;coord@pro;fromy
 */
function addTable(tableId, x, y) {
    $('#' + tableId).find('table,tr,th,td').each(function() {
        $(this).attr('style', getCalculatedCss($(this)));
    });

    //console.log(parseFloat($('#' + tableId + ' table').width()) + 5);
    var data = '<svg xmlns="http://www.w3.org/2000/svg" width="' + (parseFloat($('#' + tableId + ' table').width()) + 5) + 'px" height="' + (parseFloat($('#' + tableId + ' table').height()) + 5) + 'px">' +
            '<foreignObject width="' + (parseFloat($('#' + tableId + ' table').width()) + 5) + 'px" height="' + (parseFloat($('#' + tableId + ' table').height()) + 5) + 'px"><div xmlns="http://www.w3.org/1999/xhtml">' +
            $("#" + tableId).html() +
            '</div></foreignObject></svg>';
    var DOMURL = self.URL || self.webkitURL || self;
    var img = new Image();
    var svg = new Blob([data], {
        type: "image/svg+xml;charset=utf-8"
    });
    var url = DOMURL.createObjectURL(svg);
    img.onload = function() {
        drawImage(this, tableId, x, y);
        DOMURL.revokeObjectURL(url);
        updateLines(tableId);
        centerGraph();
    };
    img.src = url;
}

/**
 * Declare the Image to the Kinatic layer
 * @param {type} tableFrom
 * @param {type} tableTo
 * @returns {getStartAndEndLine.coord}
 */
function drawImage(img, tableId, x, y) {
    var kImg = new Kinetic.Image({
        image: img,
        x: x,
        y: y,
        width: $('#' + tableId + ' table').width(),
        height: $('#' + tableId + ' table').height(),
        draggable: true,
        id: tableId
    });

    // add cursor styling
    kImg.on('mouseover', function() {
        document.body.style.cursor = 'pointer';
    });
    kImg.on('mouseout', function() {
        document.body.style.cursor = 'default';
    });
    kImg.on('mouseup', function() {
        updateLines(tableId);
    });
    layer.add(kImg);
    stage.add(layer);
}

/**
 * Redraw lines connected to the dragged table
 * @param {type} tableFrom
 * @param {type} tableTo
 * @returns {getStartAndEndLine.coord}
 */
function updateLines(tableId) {
    var regExp = new RegExp(tableId);
    for (var i = 0; i < lines.length; i++) {

        if (regExp.test(lines[i])) {
            var tables = lines[i].split('-');

            var line = stage.find('#' + lines[i])[0];
            var tableFrom = stage.find('#' + tables[0])[0];
            var tableTo = stage.find('#' + tables[1])[0];
            if (tableFrom && tableTo) {
                coord = getStartAndEndLine(tableFrom, tableTo);
                var fromx = coord.fromx;
                var fromy = coord.fromy;
                var tox = coord.tox;
                var toy = coord.toy;
                //console.log([fromx, fromy, tox, toy]);
                var headlen = 20;   // how long you want the head of the arrow to be, you could calculate this as a fraction of the distance between the points as well.
                var angle = Math.atan2(toy - fromy, tox - fromx);
                line.setPoints([fromx, fromy, tox, toy, tox - headlen * Math.cos(angle - Math.PI / 6), toy - headlen * Math.sin(angle - Math.PI / 6), tox, toy, tox - headlen * Math.cos(angle + Math.PI / 6), toy - headlen * Math.sin(angle + Math.PI / 6)]);
                layer.drawScene();
            }
        }
    }

}

function centerGraph() {
    var minX = 10000;
    var maxX = 10;
    var minY = 10000;
    var maxY = 10;
    for (index in tables) {
        var item = stage.find('#' + tables[index])[0];
        if (item !== undefined) {
            if (item.getPosition().x < minX) {
                minX = item.getPosition().x;
            }

            if (item.getPosition().x + item.getWidth() > maxX) {
                maxX = item.getX() + item.getWidth();
            }

            if (item.getPosition().y < minY) {
                minY = item.getPosition().y;
            }

            if (item.getPosition().y + item.getHeight() > maxY) {
                maxY = item.getPosition().y + item.getHeight();
            }
        }
    }

    //console.log(minX +'-'+maxX+'-'+minY+'-'+maxY);
    for (index in tables) {
        var item = stage.find('#' + tables[index])[0];
        if (item !== undefined) {
            var newPosition = {x: 0, y: 0};
            newPosition.x = item.getX() - minX + 10;
            newPosition.y = item.getY() - minY + 10;
            //console.log(item.getId()+' from x ' +item.getPosition().x+' to '+newPosition.x);
            //console.log(item.getId()+' from y ' +item.getPosition().y+' to '+newPosition.y);
            item.setX(newPosition.x);
            item.setY(newPosition.y);
            updateLines(item.getId());
        }
    }
    stage.setHeight(maxY + 50);
    stage.setWidth(maxX + 50);
}

/**
 * Determine where to attach the line extremities to the tables
 * @param {Image} tableFrom
 * @param {Image} tableTo
 * @returns {String}
 */
function getStartAndEndLine(tableFrom, tableTo) {
    var coord = {fromx: 0, fromy: 0, tox: 0, toy: 0};

    if (tableFrom.getY() * Math.sin(45) > tableTo.getY() * Math.sin(45) && tableFrom.getX() * Math.cos(45) > tableTo.getX() * Math.cos(45)) {
        //console.log('case 1 : Top Left');
        coord.fromx = Math.round(tableFrom.getX());
        coord.fromy = Math.round((tableFrom.getY() + tableFrom.getHeight() / 2));
        coord.tox = Math.round(tableTo.getX() + tableTo.getWidth());
        coord.toy = Math.round(tableTo.getY() + tableTo.getHeight() / 2);
    } else if (tableFrom.getY() * Math.sin(45) <= tableTo.getY() * Math.sin(45) && tableFrom.getX() * Math.cos(45) <= tableTo.getX() * Math.cos(45)) {
        //console.log('case 3 : Bottom Right');
        coord.fromx = Math.round(tableFrom.getX() + tableFrom.getWidth());
        coord.fromy = Math.round((tableFrom.getY() + tableFrom.getHeight() / 2));
        coord.tox = Math.round(tableTo.getX());
        coord.toy = Math.round(tableTo.getY() + tableTo.getHeight() / 2);
    } else if (tableFrom.getY() * Math.sin(45) <= tableTo.getY() * Math.sin(45) && tableFrom.getX() * Math.cos(45) > tableTo.getX() * Math.cos(45)) {
        //console.log('case 4 : Bottom Left ');
        coord.fromx = Math.round(tableFrom.getX() + tableFrom.getWidth() / 2);
        coord.fromy = Math.round((tableFrom.getY() + tableFrom.getHeight()));
        coord.tox = Math.round(tableTo.getX() + tableTo.getWidth() / 2);
        coord.toy = Math.round(tableTo.getY());
    } else if (tableFrom.getY() * Math.sin(45) > tableTo.getY() * Math.sin(45) && tableFrom.getX() * Math.cos(45) <= tableTo.getX() * Math.cos(45)) {
        //console.log('case 2 : Top Right');
        coord.fromx = Math.round(tableFrom.getX() + tableFrom.getWidth() / 2);
        coord.fromy = Math.round((tableFrom.getY()));
        coord.tox = Math.round(tableTo.getX() + tableTo.getWidth() / 2);
        coord.toy = Math.round(tableTo.getY() + tableTo.getHeight());
    }
    return coord;
}

/**
 * Draw a line terminated by an arrow
 * @param {type} id
 * @param {type} fromx
 * @param {type} fromy
 * @param {type} tox
 * @param {type} toy
 * @returns {undefined}
 */
function arrow(id, fromx, fromy, tox, toy) {
    var headlen = 20;   // how long you want the head of the arrow to be, you could calculate this as a fraction of the distance between the points as well.
    var angle = Math.atan2(toy - fromy, tox - fromx);

    var line = new Kinetic.Line({
        points: [fromx, fromy, tox, toy, tox - headlen * Math.cos(angle - Math.PI / 6), toy - headlen * Math.sin(angle - Math.PI / 6), tox, toy, tox - headlen * Math.cos(angle + Math.PI / 6), toy - headlen * Math.sin(angle + Math.PI / 6)],
        stroke: "black",
        id: id
    });
    lines.push(id);
    layer.add(line);
}
/**
 * Get all the calculated CSS as a string ready to be put in a style tag
 * @param jQuery element
 * @returns {String}
 */
function getCalculatedCss(element) {
    var css = '';
    if (window.getComputedStyle) {
        var camelize = function(a, b) {
            return b.toUpperCase();
        }
        style = window.getComputedStyle(element.get(0), null);
        for (var i = 0, l = style.length; i < l; i++) {
            var prop = style[i];
            //var camel = prop.replace(/\-([a-z])/g, camelize);
            var val = style.getPropertyValue(prop);
            css += prop + ":" + val + ';';
        }
        return css;

    }

    if (style = element.currentStyle) {
        for (var prop in style) {
            css += prop + ":" + style[prop] + ';';
        }

        return css;
    }

}


