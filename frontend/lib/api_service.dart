import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;

class ApiService {
  // ⚙️ Adresse de ton backend Symfony
  final String baseUrl = 'http://127.0.0.1:8000';
  // Pour Android :
  // final String baseUrl = 'http://10.0.2.2:8000';

  // ===============================
  // 🧩 Enregistrement d’un utilisateur standard
  // ===============================
  Future<String> registerUser(String email, String password) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/api/register'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'email': email,
          'password': password,
          'role': 'ROLE_USER',
        }),
      );

      if (response.statusCode >= 200 && response.statusCode < 300) {
        return "✅ Compte utilisateur créé avec succès !";
      }

      final body = jsonDecode(response.body);
      if (body is Map && body.containsKey('error')) {
        return body['error'].toString();
      }

      return "❌ Erreur ${response.statusCode}: ${response.reasonPhrase}";
    } catch (e) {
      return "🚫 Erreur réseau : ${e.toString()}";
    }
  }

  // ===============================
  // 🧩 Enregistrement d’un professionnel
  // ===============================
  Future<Map<String, dynamic>> registerProfessional({
    required String email,
    required String password,
    required String fullName,
    required int specialityId,
    required String zone,
    required double pricePerHour,
    required String siret,
    required String phone,
  }) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/api/professionals'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'email': email,
          'password': password,
          'fullName': fullName,
          'specialityId': specialityId,
          'zone': zone,
          'pricePerHour': pricePerHour,
          'siret': siret,
          'phoneNumber': phone,
        }),
      );

      // ✅ Succès
      if (response.statusCode >= 200 && response.statusCode < 300) {
        final body = jsonDecode(response.body);
        return {
          'success': true,
          'message': body['message'] ?? '✅ Inscription professionnelle réussie !',
          'proId': body['professional']?['id'],
        };
      }

      // ⚠️ Lecture des erreurs
      dynamic body;
      try {
        body = jsonDecode(response.body);
      } catch (_) {
        return {
          'success': false,
          'message': "❌ Erreur ${response.statusCode}: ${response.reasonPhrase}",
        };
      }

      // 🧩 Violations Symfony (validation)
      if (body is Map && body.containsKey('violations')) {
        final violations = body['violations'] as Map<String, dynamic>;
        final List<String> messages = [];

        violations.forEach((field, errors) {
          if (errors is List) {
            for (var msg in errors) {
              messages.add("• $msg");
            }
          } else {
            messages.add("• $errors");
          }
        });

        return {
          'success': false,
          'message': "⚠️ ${body['error'] ?? 'Erreurs de validation'} :\n${messages.join("\n")}",
        };
      }

      // 🧩 Autre type d'erreur
      if (body is Map && body.containsKey('error')) {
        return {
          'success': false,
          'message': "❌ ${body['error']}",
        };
      }

      // ❌ Fallback générique
      return {
        'success': false,
        'message': "❌ Erreur ${response.statusCode}: ${response.reasonPhrase}",
      };
    } catch (e) {
      return {
        'success': false,
        'message': "🚫 Erreur réseau : ${e.toString()}",
      };
    }
  }

  // ===============================
  // 🔐 Connexion utilisateur
  // ===============================
  Future<String> login(String email, String password) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/api/login'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({'email': email, 'password': password}),
      );

      if (response.statusCode >= 200 && response.statusCode < 300) {
        final body = jsonDecode(response.body);
        return "✅ Connexion réussie ! Token : ${body['token'] ?? 'non disponible'}";
      }

      final body = jsonDecode(response.body);
      if (body is Map && body.containsKey('message')) {
        return "❌ ${body['message']}";
      }

      return "❌ Erreur ${response.statusCode}: ${response.reasonPhrase}";
    } catch (e) {
      return "🚫 Erreur réseau : ${e.toString()}";
    }
  }

  // ===============================
  // 📋 Récupération des spécialités
  // ===============================
  Future<List<Map<String, dynamic>>> fetchSpecialities() async {
    try {
      final response = await http.get(
        Uri.parse('$baseUrl/api/specialities'),
        headers: {'Content-Type': 'application/json'},
      );

      if (response.statusCode == 200) {
        final List list = jsonDecode(response.body);
        return List<Map<String, dynamic>>.from(list);
      } else {
        throw Exception(
          "Erreur ${response.statusCode}: ${response.reasonPhrase}",
        );
      }
    } catch (e) {
      throw Exception("Erreur réseau : $e");
    }
  }

  // ===============================
  // 📸 Upload de la photo de profil
  // ===============================
  Future<String> uploadProfilePicture({
    required int proId,
    required File imageFile,
  }) async {
    try {
      var request = http.MultipartRequest(
        'POST',
        Uri.parse('$baseUrl/api/professionals/$proId/upload'),
      );

      request.files.add(await http.MultipartFile.fromPath('file', imageFile.path));
      final response = await request.send();

      final responseBody = await response.stream.bytesToString();
      if (response.statusCode >= 200 && response.statusCode < 300) {
        return "✅ Photo envoyée avec succès";
      } else {
        return "❌ Erreur lors de l'envoi : ${response.statusCode}\n$responseBody";
      }
    } catch (e) {
      return "🚫 Erreur réseau : ${e.toString()}";
    }
  }
}
