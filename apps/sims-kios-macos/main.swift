import AppKit
import AutomaticAssessmentConfiguration
import CryptoKit
import WebKit

final class KiosApp: NSObject, NSApplicationDelegate, NSWindowDelegate, WKNavigationDelegate,
    WKScriptMessageHandlerWithReply, AEAssessmentSessionDelegate {
    private let start: URL
    private let period: String
    private var assessment: AEAssessmentSession?
    private var pendingReply: ((Any?, String?) -> Void)?
    private var pendingMessage: String?
    private var finishing = false
    private let window = NSWindow(
        contentRect: NSRect(x: 0, y: 0, width: 980, height: 780),
        styleMask: [.titled, .closable, .resizable, .miniaturizable], backing: .buffered, defer: false
    )
    private var web: WKWebView!

    init(start: URL, period: String) {
        self.start = start
        self.period = period
    }

    func applicationDidFinishLaunching(_ notification: Notification) {
        let config = WKWebViewConfiguration()
        config.websiteDataStore = .nonPersistent()
        config.preferences.javaScriptCanOpenWindowsAutomatically = false
        config.userContentController.addScriptMessageHandler(self, contentWorld: .page, name: "simsKios")
        web = WKWebView(frame: .zero, configuration: config)
        web.navigationDelegate = self
        let recovery = NSButton(title: "Bantuan pemandu", target: self, action: #selector(keluarDarurat))
        recovery.setContentHuggingPriority(.required, for: .vertical)
        let container = NSStackView(views: [web, recovery])
        container.orientation = .vertical
        container.alignment = .trailing
        container.edgeInsets = NSEdgeInsets(top: 0, left: 0, bottom: 8, right: 8)
        web.widthAnchor.constraint(equalTo: container.widthAnchor, constant: -8).isActive = true
        window.title = "SIMS Kios"
        window.delegate = self
        window.contentView = container
        window.center()
        window.makeKeyAndOrderFront(nil)

        let main = NSMenu()
        let app = NSMenuItem()
        main.addItem(app)
        let menu = NSMenu()
        app.submenu = menu
        let bantuan = NSMenuItem(title: "Keluar dengan kata sandi pemandu", action: #selector(keluarDarurat), keyEquivalent: "q")
        bantuan.target = self
        menu.addItem(bantuan)
        NSApp.mainMenu = main
        NSApp.activate(ignoringOtherApps: true)
        web.load(URLRequest(url: start))
    }

    func applicationShouldTerminate(_ sender: NSApplication) -> NSApplication.TerminateReply {
        assessment == nil ? .terminateNow : .terminateCancel
    }

    func windowShouldClose(_ sender: NSWindow) -> Bool {
        if assessment == nil { return true }
        keluarDarurat()
        return false
    }

    func applicationShouldTerminateAfterLastWindowClosed(_ sender: NSApplication) -> Bool { true }

    private func sameOrigin(_ url: URL) -> Bool {
        url.scheme == start.scheme && url.host == start.host && url.port == start.port
            && url.user == nil && url.password == nil
    }

    private var rootPath: String { String(start.path.dropLast("/masuk/".count + period.count)) }
    private var periodPath: String { "\(rootPath)/periode/\(period)" }
    private var formPath: String { "\(periodPath)/isi" }
    private var donePath: String { "\(periodPath)/selesai-kios" }

    func webView(_ webView: WKWebView, decidePolicyFor action: WKNavigationAction,
                 decisionHandler: @escaping (WKNavigationActionPolicy) -> Void) {
        guard let url = action.request.url, sameOrigin(url), action.targetFrame?.isMainFrame == true else {
            decisionHandler(.cancel)
            return
        }
        let loginPath = String(rootPath.dropLast("/survei-pembelajaran".count)) + "/login"
        let paths = [loginPath, start.path, formPath, donePath, "\(periodPath)/token"]
        decisionHandler(paths.contains(url.path) ? .allow : .cancel)
    }

    func webView(_ webView: WKWebView, decidePolicyFor response: WKNavigationResponse,
                 decisionHandler: @escaping (WKNavigationResponsePolicy) -> Void) {
        guard let url = response.response.url, sameOrigin(url) else {
            decisionHandler(.cancel)
            return
        }
        if response.isForMainFrame, let http = response.response as? HTTPURLResponse,
           url.path == donePath, http.statusCode == 200 {
            decisionHandler(.cancel)
            selesai()
            return
        }
        decisionHandler(response.canShowMIMEType ? .allow : .cancel)
    }

    func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
        if (error as NSError).code != NSURLErrorCancelled {
            tampilkanPesan("Koneksi ke SIMS terputus.", info: "Periksa koneksi lalu coba lagi.", retry: true)
        }
    }

    func webViewWebContentProcessDidTerminate(_ webView: WKWebView) {
        tampilkanPesan("Halaman SIMS terhenti.", info: "Muat ulang untuk membuka kembali survei.", retry: true)
    }

    func userContentController(_ userContentController: WKUserContentController, didReceive message: WKScriptMessage,
                               replyHandler: @escaping (Any?, String?) -> Void) {
        guard message.frameInfo.isMainFrame, let url = message.frameInfo.request.url, sameOrigin(url),
              url.path == formPath, let values = message.body as? [String: String],
              let action = values["action"], ["buka", "kirim"].contains(action), values["period"] == period,
              let nonce = values["nonce"], nonce.utf8.count == 48,
              let token = values["session"], token.utf8.count == 64 else {
            replyHandler(nil, "Permintaan penguncian tidak valid.")
            return
        }

        let payload = ["sims-kios-v1", period, action, nonce, token].joined(separator: "|")
        if assessment?.isActive == true && !finishing {
            sign(payload, reply: replyHandler)
            return
        }
        guard action == "buka", assessment == nil, DeviceKey.existing() != nil,
              recoveryHash != nil else {
            replyHandler(nil, "Perangkat belum siap dikunci.")
            tampilkanPesan("Perangkat belum siap dikunci.", info: "Hubungi pemandu untuk mendaftarkan perangkat dan menyiapkan kata sandi pemulihan.")
            return
        }
        pendingMessage = payload
        pendingReply = replyHandler
        let session = AEAssessmentSession(configuration: AEAssessmentConfiguration())
        session.delegate = self
        assessment = session
        session.begin()
    }

    func assessmentSessionDidBegin(_ session: AEAssessmentSession) {
        guard session === assessment, session.isActive, let reply = pendingReply, let payload = pendingMessage else { return }
        pendingReply = nil
        pendingMessage = nil
        sign(payload, reply: reply)
    }

    func assessmentSession(_ session: AEAssessmentSession, failedToBeginWithError error: Error) {
        guard session === assessment else { return }
        assessment = nil
        pendingReply?(nil, "macOS menolak penguncian.")
        pendingReply = nil
        pendingMessage = nil
        tampilkanPesan("macOS menolak penguncian.", info: "Soal tetap tertutup. Gunakan versi aplikasi dengan izin Assessment Mode yang disetujui Apple.")
    }

    func assessmentSession(_ session: AEAssessmentSession, wasInterruptedWithError error: Error) {
        guard session === assessment else { return }
        pendingReply?(nil, "Penguncian terhenti.")
        pendingReply = nil
        pendingMessage = nil
        web.stopLoading()
        tampilkanPesan("Penguncian perangkat terhenti.", info: "Hubungi pemandu survei.")
        session.end()
    }

    func assessmentSessionDidEnd(_ session: AEAssessmentSession) {
        guard session === assessment else { return }
        assessment = nil
        if finishing { NSApp.terminate(nil) }
    }

    private func sign(_ payload: String, reply: (Any?, String?) -> Void) {
        guard let signature = DeviceKey.sign(payload) else {
            reply(nil, "Perangkat belum terdaftar.")
            return
        }
        reply(signature, nil)
    }

    private func selesai() {
        finishing = true
        web.stopLoading()
        if let assessment { assessment.end() }
        else { NSApp.terminate(nil) }
    }

    private var recoveryHash: String? {
        let defaults = UserDefaults.standard
        let key = "RecoveryPasswordSHA256"
        guard start.host == "127.0.0.1" || defaults.objectIsForced(forKey: key) else { return nil }
        let value = defaults.string(forKey: key)
        return value?.range(of: "^[a-f0-9]{64}$", options: .regularExpression) != nil ? value : nil
    }

    @objc private func keluarDarurat() {
        guard assessment != nil else {
            tampilkanPesan("Bantuan pemandu", info: "Belum ada sesi penguncian aktif.")
            return
        }
        let input = NSSecureTextField(frame: NSRect(x: 0, y: 0, width: 240, height: 26))
        let alert = NSAlert()
        alert.messageText = "Kata sandi pemandu"
        alert.informativeText = "Untuk membuka perangkat sebelum pengiriman selesai."
        alert.accessoryView = input
        alert.addButton(withTitle: "Buka perangkat")
        alert.addButton(withTitle: "Kembali")
        alert.window.initialFirstResponder = input
        guard alert.runModal() == .alertFirstButtonReturn else { return }
        let hash = SHA256.hash(data: Data(input.stringValue.utf8)).map { String(format: "%02x", $0) }.joined()
        guard hash == recoveryHash else { NSSound.beep(); return }
        selesai()
    }

    private func tampilkanPesan(_ title: String, info: String, retry: Bool = false) {
        let alert = NSAlert()
        alert.messageText = title
        alert.informativeText = info
        alert.addButton(withTitle: retry ? "Muat ulang" : "Tutup pesan")
        if retry { alert.addButton(withTitle: "Tutup pesan") }
        alert.beginSheetModal(for: window) { [weak self] result in
            guard retry, result == .alertFirstButtonReturn, let self else { return }
            self.web.load(URLRequest(url: self.start))
        }
    }
}

let arguments = Array(CommandLine.arguments.dropFirst())
if arguments == ["--register-device"] {
    guard let publicKey = DeviceKey.register() else {
        FileHandle.standardError.write(Data("Gagal membuat kunci perangkat.\n".utf8))
        exit(1)
    }
    print(publicKey)
    exit(0)
}

let defaultURL = Bundle.main.object(forInfoDictionaryKey: "SIMSSurveyURL") as? String
let startURL = arguments.first ?? defaultURL ?? ""
let prefix = "/survei-pembelajaran/masuk/"
guard let start = URL(string: startURL), let host = start.host,
      start.scheme == "https" || (start.scheme == "http" && host == "127.0.0.1"),
      start.user == nil, start.password == nil, start.query == nil, start.fragment == nil,
      let range = start.path.range(of: prefix, options: .backwards),
      let id = UUID(uuidString: String(start.path[range.upperBound...])) else {
    let app = NSApplication.shared
    app.setActivationPolicy(.regular)
    let alert = NSAlert()
    alert.messageText = "Tautan survei belum diatur"
    alert.informativeText = "Minta pemandu menyiapkan aplikasi dengan tautan masuk survei SIMS."
    alert.runModal()
    exit(1)
}

let app = NSApplication.shared
let delegate = KiosApp(start: start, period: id.uuidString.lowercased())
app.delegate = delegate
app.setActivationPolicy(.regular)
app.run()
