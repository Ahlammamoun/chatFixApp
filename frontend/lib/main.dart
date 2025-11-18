import 'package:flutter/material.dart';
import 'pages/login_page.dart';
import 'pages/user_register_page.dart';
import 'pages/professional_register_page.dart';

void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      home: const HomePage(),
    );
  }
}

class HomePage extends StatelessWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Bienvenue')),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            ElevatedButton(
              child: const Text('Créer un compte utilisateur'),
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const UserRegisterPage()),
              ),
            ),
            const SizedBox(height: 12),
            ElevatedButton(
              child: const Text('Créer un compte professionnel'),
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const ProfessionalRegisterPage()),
              ),
            ),
            const SizedBox(height: 12),
            ElevatedButton(
              child: const Text('Se connecter'),
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const LoginPage()),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
