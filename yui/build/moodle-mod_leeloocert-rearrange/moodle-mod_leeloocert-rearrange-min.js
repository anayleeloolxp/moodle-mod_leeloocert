YUI.add(
  "moodle-mod_leeloocert-rearrange",
  function (r, t) {
    var s = function () {
      s.superclass.constructor.apply(this, [arguments]);
    };
    r.extend(s, r.Base, {
      templateid: 0,
      page: [],
      elements: [],
      pdfx: 0,
      pdfy: 0,
      pdfwidth: 0,
      pdfheight: 0,
      elementxy: 0,
      pdfleftboundary: 0,
      pdfrightboundary: 0,
      pixelsinmm: 3.779527559055,
      initializer: function (t) {
        (this.templateid = t[0]),
          (this.page = t[1]),
          (this.elements = t[2]),
          this.setPdfDimensions(),
          this.setBoundaries(),
          this.setpositions(),
          this.createevents(),
          window.addEventListener("resize", this.checkWindownResize.bind(this));
      },
      setpositions: function () {
        var t, e, i, s, n, o;
        for (t in this.elements) {
          switch (
            ((e = this.elements[t]),
            (i = this.pdfx + e.posx * this.pixelsinmm),
            (s = this.pdfy + e.posy * this.pixelsinmm),
            (n = parseFloat(
              r.one("#element-" + e.id).getComputedStyle("width")
            )),
            (o = e.width * this.pixelsinmm) && o < n && (n = o),
            e.refpoint)
          ) {
            case "1":
              i -= n / 2;
              break;
            case "2":
              i = i - n + 2;
          }
          r.one("#element-" + e.id).setX(i), r.one("#element-" + e.id).setY(s);
        }
      },
      setPdfDimensions: function () {
        (this.pdfx = r.one("#pdf").getX()),
          (this.pdfy = r.one("#pdf").getY()),
          (this.pdfwidth = parseFloat(r.one("#pdf").getComputedStyle("width"))),
          (this.pdfheight = parseFloat(
            r.one("#pdf").getComputedStyle("height")
          ));
      },
      setBoundaries: function () {
        (this.pdfleftboundary = this.pdfx),
          this.page.leftmargin &&
            (this.pdfleftboundary += parseInt(
              this.page.leftmargin * this.pixelsinmm,
              10
            )),
          (this.pdfrightboundary = this.pdfx + this.pdfwidth),
          this.page.rightmargin &&
            (this.pdfrightboundary -= parseInt(
              this.page.rightmargin * this.pixelsinmm,
              10
            ));
      },
      checkWindownResize: function () {
        this.setPdfDimensions(), this.setBoundaries(), this.setpositions();
      },
      createevents: function () {
        r.one(".savepositionsbtn [type=submit]").on(
          "click",
          function (t) {
            this.savepositions(t);
          },
          this
        ),
          r.one(".applypositionsbtn [type=submit]").on(
            "click",
            function (t) {
              this.savepositions(t), t.preventDefault();
            },
            this
          );
        var e = new r.DD.Delegate({ container: "#pdf", nodes: ".element" });
        e.on(
          "drag:start",
          function () {
            var t = e.get("currentNode");
            this.elementxy = t.getXY();
          },
          this
        ),
          e.on(
            "drag:end",
            function () {
              var t = e.get("currentNode");
              this.isoutofbounds(t) && t.setXY(this.elementxy);
            },
            this
          );
      },
      isoutofbounds: function (t) {
        var e = parseFloat(t.getComputedStyle("width")),
          i = parseFloat(t.getComputedStyle("height")),
          s = t.getX(),
          n = s + e,
          o = t.getY(),
          a = o + i;
        return (
          s < this.pdfleftboundary ||
          n > this.pdfrightboundary ||
          o < this.pdfy ||
          a > this.pdfy + this.pdfheight
        );
      },
      savepositions: function (o) {
        var t,
          e,
          i,
          s,
          n,
          a,
          d,
          p = { tid: this.templateid, values: [] };
        for (t in this.elements) {
          switch (
            ((e = this.elements[t]),
            (s = (i = r.one("#element-" + e.id)).getX() - this.pdfx),
            (n = i.getY() - this.pdfy),
            (a = i.getData("refpoint")),
            (d = parseFloat(i.getComputedStyle("width"))),
            a)
          ) {
            case "1":
              s += d / 2;
              break;
            case "2":
              s += d;
          }
          p.values.push({
            id: e.id,
            posx: Math.round(parseFloat(s / this.pixelsinmm)),
            posy: Math.round(parseFloat(n / this.pixelsinmm)),
          });
        }
        (p.values = JSON.stringify(p.values)),
          r.io(M.cfg.wwwroot + "/mod/leeloocert/ajax.php", {
            method: "POST",
            data: p,
            on: {
              failure: function (t, e) {
                this.ajaxfailure(e);
              },
              success: function () {
                var t,
                  e,
                  i = o.currentTarget.ancestor("form", !0),
                  s = i.getAttribute("action"),
                  n = i.one("[name=pid]");
                n
                  ? ((t = n.get("value")), (window.location = s + "?pid=" + t))
                  : ((e = i.one("[name=tid]").get("value")),
                    (window.location = s + "?tid=" + e));
              },
            },
            context: this,
          }),
          o.preventDefault();
      },
      ajaxfailure: function (t) {
        var e = {
          name: t.status + " " + t.statusText,
          message: t.responseText,
        };
        return new M.core.exception(e);
      },
    }),
      (r.namespace("M.mod_leeloocert.rearrange").init = function (t, e, i) {
        new s(t, e, i);
      });
  },
  "@VERSION@",
  { requires: ["dd-delegate", "dd-drag"] }
);
