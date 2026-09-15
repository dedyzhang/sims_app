
let bytes = "\xA5\xCA\xB6\x88\x00\x7F";
try {
    let str = decodeURIComponent(escape(bytes));
    console.log(str);
} catch (e) {
    console.log("Error:", e.message);
}

