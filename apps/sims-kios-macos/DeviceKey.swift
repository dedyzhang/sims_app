import Foundation
import Security

enum DeviceKey {
    private static let tag = Data("id.edutive.sims.kios.signing".utf8)

    private static let query: [String: Any] = [
        kSecClass as String: kSecClassKey,
        kSecAttrApplicationTag as String: tag,
        kSecAttrKeyType as String: kSecAttrKeyTypeECSECPrimeRandom,
        kSecAttrKeyClass as String: kSecAttrKeyClassPrivate,
        kSecReturnRef as String: true,
    ]

    static func existing() -> SecKey? {
        var result: CFTypeRef?
        guard SecItemCopyMatching(query as CFDictionary, &result) == errSecSuccess, let result else { return nil }
        return (result as! SecKey)
    }

    static func register() -> String? {
        let key = existing() ?? SecKeyCreateRandomKey([
            kSecAttrKeyType as String: kSecAttrKeyTypeECSECPrimeRandom,
            kSecAttrKeySizeInBits as String: 256,
            kSecPrivateKeyAttrs as String: [
                kSecAttrIsPermanent as String: true,
                kSecAttrApplicationTag as String: tag,
                kSecAttrIsExtractable as String: false,
            ],
        ] as CFDictionary, nil)
        guard let key, let publicKey = SecKeyCopyPublicKey(key),
              let rawKey = SecKeyCopyExternalRepresentation(publicKey, nil) else { return nil }

        // DER SubjectPublicKeyInfo for an uncompressed P-256 public point.
        let header: [UInt8] = [
            0x30, 0x59, 0x30, 0x13, 0x06, 0x07, 0x2a, 0x86, 0x48, 0xce, 0x3d, 0x02, 0x01,
            0x06, 0x08, 0x2a, 0x86, 0x48, 0xce, 0x3d, 0x03, 0x01, 0x07, 0x03, 0x42, 0x00,
        ]
        return (Data(header) + (rawKey as Data)).base64EncodedString()
    }

    static func sign(_ message: String) -> String? {
        guard let key = existing(),
              let signature = SecKeyCreateSignature(
                key, .ecdsaSignatureMessageX962SHA256, Data(message.utf8) as CFData, nil
              ) else { return nil }
        return (signature as Data).base64EncodedString()
    }
}
