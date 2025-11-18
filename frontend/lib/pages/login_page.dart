import 'package:flutter/material.dart';
import '../api_service.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _api = ApiService();
  final _email = TextEditingController();
  final _password = TextEditingController();
  String _result = '';

  Future<void> _login() async {
    final res = await _api.login(
      _email.text.trim(),
      _password.text.trim(),
    );
    setState(() => _result = res.toString());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Connexion')),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: SingleChildScrollView(
          child: Column(
            children: [
              TextField(
                controller: _email,
                decoration: const InputDecoration(labelText: 'Email'),
              ),
              TextField(
                controller: _password,
                decoration: const InputDecoration(labelText: 'Mot de passe'),
                obscureText: true,
              ),
              const SizedBox(height: 20),
              ElevatedButton(
                onPressed: _login,
                child: const Text('Se connecter'),
              ),
              const SizedBox(height: 20),
              if (_result.isNotEmpty)
                SelectableText(
                  _result,
                  style: const TextStyle(color: Colors.blueAccent),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
