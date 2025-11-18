import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../api_service.dart';

class ProfessionalRegisterPage extends StatefulWidget {
  const ProfessionalRegisterPage({super.key});

  @override
  State<ProfessionalRegisterPage> createState() =>
      _ProfessionalRegisterPageState();
}

class _ProfessionalRegisterPageState extends State<ProfessionalRegisterPage> {
  final _api = ApiService();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _fullName = TextEditingController();
  final _zone = TextEditingController();
  final _price = TextEditingController();
  final _siret = TextEditingController();
  final _phone = TextEditingController();

  List<Map<String, dynamic>> _specialities = [];
  int? _selectedSpecialityId;

  File? _selectedImage;
  final ImagePicker _picker = ImagePicker();

  String _message = '';
  bool _isSuccess = false;
  bool _isLoading = false;
  bool _isFetchingSpecialities = true;

  @override
  void initState() {
    super.initState();
    _loadSpecialities();
  }

  Future<void> _loadSpecialities() async {
    try {
      final list = await _api.fetchSpecialities();
      setState(() {
        _specialities = list;
        _isFetchingSpecialities = false;
      });
    } catch (e) {
      setState(() {
        _message = "Erreur lors du chargement des spécialités : $e";
        _isFetchingSpecialities = false;
      });
    }
  }

  Future<void> _pickImage() async {
    final pickedFile = await _picker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 80,
    );
    if (pickedFile != null) {
      setState(() => _selectedImage = File(pickedFile.path));
    }
  }

  Future<void> _register() async {
    if (_selectedSpecialityId == null) {
      setState(() {
        _message = "⚠️ Veuillez choisir une spécialité";
        _isSuccess = false;
      });
      return;
    }

    setState(() {
      _message = "Envoi en cours...";
      _isSuccess = false;
      _isLoading = true;
    });

    final result = await _api.registerProfessional(
      email: _email.text.trim(),
      password: _password.text.trim(),
      fullName: _fullName.text.trim(),
      specialityId: _selectedSpecialityId!,
      zone: _zone.text.trim(),
      pricePerHour: double.tryParse(_price.text.trim()) ?? 0,
      siret: _siret.text.trim(),
      phone: _phone.text.trim(),
    );

    setState(() {
      _message = result['message'];
      _isSuccess = result['success'];
      _isLoading = false;
    });

    // ✅ Upload de la photo après création du compte
    if (_isSuccess && _selectedImage != null && result['proId'] != null) {
      final uploadMsg = await _api.uploadProfilePicture(
        proId: result['proId'],
        imageFile: _selectedImage!,
      );
      setState(() => _message += "\n$uploadMsg");
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Inscription Professionnel')),
      body: SafeArea(
        child: _isFetchingSpecialities
            ? const Center(child: CircularProgressIndicator())
            : SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // ✅ Aperçu image
                    Center(
                      child: GestureDetector(
                        onTap: _pickImage,
                        child: CircleAvatar(
                          radius: 60,
                          backgroundImage: _selectedImage != null
                              ? FileImage(_selectedImage!)
                              : null,
                          child: _selectedImage == null
                              ? const Icon(Icons.add_a_photo, size: 40)
                              : null,
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),

                    TextField(
                      controller: _fullName,
                      decoration: const InputDecoration(
                        labelText: 'Nom complet',
                        prefixIcon: Icon(Icons.person),
                      ),
                    ),
                    const SizedBox(height: 12),

                    DropdownButtonFormField<int>(
                      value: _selectedSpecialityId,
                      decoration: const InputDecoration(
                        labelText: 'Spécialité',
                        prefixIcon: Icon(Icons.work_outline),
                      ),
                      items: _specialities
                          .map(
                            (s) => DropdownMenuItem<int>(
                              value: s['id'],
                              child: Text(s['name']),
                            ),
                          )
                          .toList(),
                      onChanged: (value) =>
                          setState(() => _selectedSpecialityId = value),
                    ),
                    const SizedBox(height: 12),

                    TextField(
                      controller: _zone,
                      decoration: const InputDecoration(
                        labelText: 'Zone géographique',
                        prefixIcon: Icon(Icons.location_on_outlined),
                      ),
                    ),
                    const SizedBox(height: 12),

                    TextField(
                      controller: _price,
                      decoration: const InputDecoration(
                        labelText: 'Prix / heure (€)',
                        prefixIcon: Icon(Icons.euro_symbol),
                      ),
                      keyboardType: TextInputType.number,
                    ),
                    const SizedBox(height: 12),

                    Row(
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _siret,
                            decoration: const InputDecoration(
                              labelText: 'Numéro SIRET',
                              prefixIcon:
                                  Icon(Icons.confirmation_number_outlined),
                              helperText: '14 chiffres sans espaces',
                              helperStyle: TextStyle(
                                fontSize: 12,
                                color: Colors.grey,
                              ),
                            ),
                            keyboardType: TextInputType.number,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Tooltip(
                          message:
                              'Le numéro SIRET doit contenir 14 chiffres sans espace.',
                          decoration: BoxDecoration(
                            color: Colors.blueGrey.shade700,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          textStyle: const TextStyle(
                            color: Colors.white,
                            fontSize: 13,
                          ),
                          child: const Icon(
                            Icons.info_outline,
                            color: Colors.blueAccent,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),

                    TextField(
                      controller: _phone,
                      decoration: const InputDecoration(
                        labelText: 'Numéro de téléphone',
                        prefixIcon: Icon(Icons.phone),
                        hintText: '+33612345678',
                      ),
                      keyboardType: TextInputType.phone,
                    ),
                    const SizedBox(height: 12),

                    TextField(
                      controller: _email,
                      decoration: const InputDecoration(
                        labelText: 'Adresse e-mail',
                        prefixIcon: Icon(Icons.email_outlined),
                      ),
                      keyboardType: TextInputType.emailAddress,
                    ),
                    const SizedBox(height: 12),

                    TextField(
                      controller: _password,
                      decoration: const InputDecoration(
                        labelText: 'Mot de passe',
                        prefixIcon: Icon(Icons.lock_outline),
                      ),
                      obscureText: true,
                    ),
                    const SizedBox(height: 24),

                    Center(
                      child: ElevatedButton.icon(
                        onPressed: _isLoading ? null : _register,
                        icon: const Icon(Icons.person_add_alt_1),
                        label: _isLoading
                            ? const Text("Envoi...")
                            : const Text("S'inscrire"),
                        style: ElevatedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 32,
                            vertical: 14,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),

                    if (_message.isNotEmpty)
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 300),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: _isSuccess
                              ? Colors.green.shade100
                              : Colors.red.shade100,
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(
                            color: _isSuccess
                                ? Colors.green
                                : Colors.redAccent,
                            width: 1.2,
                          ),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Icon(
                              _isSuccess
                                  ? Icons.check_circle
                                  : Icons.error_outline,
                              color: _isSuccess
                                  ? Colors.green
                                  : Colors.redAccent,
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                _message,
                                style: TextStyle(
                                  color: _isSuccess
                                      ? Colors.green.shade900
                                      : Colors.red.shade900,
                                  fontWeight: FontWeight.w600,
                                  height: 1.3,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
      ),
    );
  }
}
