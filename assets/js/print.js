/* Opens the print dialog when a document page is loaded with ?print=1 */
(function () { window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 250); }); })();
