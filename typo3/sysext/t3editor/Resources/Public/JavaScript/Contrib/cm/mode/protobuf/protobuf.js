!function(a){"object"==typeof exports&&"object"==typeof module?a(require("../../lib/codemirror")):"function"==typeof define&&define.amd?define(["../../lib/codemirror"],a):a(CodeMirror)}(function(a){"use strict";function b(a){return new RegExp("^(("+a.join(")|(")+"))\\b","i")}function c(a){if(a.eatSpace())return null;if(a.match("//"))return a.skipToEnd(),"comment";if(a.match(/^[0-9\.+-]/,!1)){if(a.match(/^[+-]?0x[0-9a-fA-F]+/))return"number";if(a.match(/^[+-]?\d*\.\d+([EeDd][+-]?\d+)?/))return"number";if(a.match(/^[+-]?\d+([EeDd][+-]?\d+)?/))return"number"}return a.match(/^"([^"]|(""))*"/)?"string":a.match(/^'([^']|(''))*'/)?"string":a.match(e)?"keyword":a.match(f)?"variable":(a.next(),null)}var d=["package","message","import","syntax","required","optional","repeated","reserved","default","extensions","packed","bool","bytes","double","enum","float","string","int32","int64","uint32","uint64","sint32","sint64","fixed32","fixed64","sfixed32","sfixed64","option","service","rpc","returns"],e=b(d);a.registerHelper("hintWords","protobuf",d);var f=new RegExp("^[_A-Za-z¡-￿][_A-Za-z0-9¡-￿]*");a.defineMode("protobuf",function(){return{token:c}}),a.defineMIME("text/x-protobuf","protobuf")});