import React, { useEffect, useState } from 'react';
import { ActivityIndicator, KeyboardAvoidingView, Modal, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as ImagePicker from 'expo-image-picker';
import { C } from '../../styles';
import { ApiError, apiRequest } from '../lib/api';
import { useNotice } from '../notice/NoticeProvider';
import UserAvatar from './UserAvatar';

type ProfileUser = {
  id: number | string;
  name: string;
  email: string;
  phone: string | null;
  email_verified: boolean;
  created_at: string | null;
  avatar_url: string | null;
  avatar_thumbnail_url: string | null;
};
type ProfileResponse = { data: { user: ProfileUser } };

export default function ProfileInfoModal({ visible, token, initialName, initialEmail, close, saveName, onAvatarChanged, embedded = false }: {
  visible: boolean;
  token: string;
  initialName: string;
  initialEmail: string;
  close: () => void;
  saveName: (name: string) => Promise<{ error?: string }>;
  onAvatarChanged?: () => Promise<void>;
  embedded?: boolean;
}) {
  const { showNotice, confirmNotice } = useNotice();
  const insets = useSafeAreaInsets();
  const [profile, setProfile] = useState<ProfileUser | null>(null);
  const [name, setName] = useState(initialName);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [avatarSaving, setAvatarSaving] = useState(false);
  const [loadError, setLoadError] = useState('');
  const trimmedName = name.trim();
  const nameValid = trimmedName.length >= 3 && trimmedName.length <= 80;
  const nameChanged = trimmedName !== (profile?.name ?? initialName).trim();

  useEffect(() => {
    if (!visible) return;
    let active = true;
    setName(initialName);
    setLoading(true);
    setLoadError('');
    apiRequest<ProfileResponse>('/auth/me', { token })
      .then(response => {
        if (!active) return;
        setProfile(response.data.user);
        setName(response.data.user.name);
      })
      .catch(error => {
        if (active) setLoadError(error instanceof ApiError ? error.message : 'Profil bilgilerine ulaşılamadı.');
      })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [initialName, token, visible]);

  const chooseAvatar = async () => {
    if (avatarSaving) return;
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      showNotice({ tone: 'warning', title: 'Fotoğraflara erişim gerekli', message: 'Profil fotoğrafı seçmek için telefon ayarlarından fotoğraf erişimine izin vermelisin.' });
      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      aspect: [1, 1],
      quality: .9,
    });
    if (result.canceled || !result.assets[0]) return;
    const asset = result.assets[0];
    if (asset.fileSize && asset.fileSize > 5 * 1024 * 1024) {
      showNotice({ tone: 'warning', title: 'Fotoğraf çok büyük', message: 'En fazla 5 MB boyutunda bir fotoğraf seçebilirsin.' });
      return;
    }
    const form = new FormData();
    form.append('avatar', {
      uri: asset.uri,
      name: asset.fileName || `profil-${Date.now()}.jpg`,
      type: asset.mimeType || 'image/jpeg',
    } as never);
    setAvatarSaving(true);
    try {
      const response = await apiRequest<ProfileResponse>('/auth/profile/avatar', { method: 'POST', token, body: form, timeoutMs: 30000 });
      setProfile(response.data.user);
      try { await onAvatarChanged?.(); } catch {}
      showNotice({ tone: 'success', title: 'Profil fotoğrafın güncellendi', message: 'Fotoğrafın profilinde, ilanlarında, mesajlaşmada ve sıralama tercihin açıksa sıralamada görünecek.' });
    } catch (error) {
      showNotice({ tone: 'error', title: 'Fotoğraf yüklenemedi', message: error instanceof ApiError ? error.message : 'Fotoğraf servisine ulaşılamadı.' });
    } finally {
      setAvatarSaving(false);
    }
  };

  const removeAvatar = async () => {
    if (!profile?.avatar_url || avatarSaving) return;
    const accepted = await confirmNotice({ tone: 'warning', title: 'Profil fotoğrafın kaldırılsın mı?', message: 'Fotoğrafın silinir ve uygulamada yeniden adının baş harfi gösterilir.', primaryLabel: 'Fotoğrafı kaldır', secondaryLabel: 'Vazgeç' });
    if (!accepted) return;
    setAvatarSaving(true);
    try {
      const response = await apiRequest<ProfileResponse>('/auth/profile/avatar', { method: 'DELETE', token });
      setProfile(response.data.user);
      try { await onAvatarChanged?.(); } catch {}
      showNotice({ tone: 'success', title: 'Profil fotoğrafın kaldırıldı', message: 'Artık profilinde adının baş harfi gösterilecek.' });
    } catch (error) {
      showNotice({ tone: 'error', title: 'Fotoğraf kaldırılamadı', message: error instanceof ApiError ? error.message : 'Fotoğraf servisine ulaşılamadı.' });
    } finally {
      setAvatarSaving(false);
    }
  };

  const submit = async () => {
    if (!nameValid || !nameChanged || saving) return;
    setSaving(true);
    const result = await saveName(trimmedName);
    setSaving(false);
    if (result.error) {
      showNotice({ tone: 'error', title: 'Profil güncellenemedi', message: result.error });
      return;
    }
    setProfile(current => current ? { ...current, name: trimmedName } : current);
    showNotice({ tone: 'success', title: 'Profilin güncellendi', message: 'Ad ve soyadın uygulamanın her yerinde güncellendi.' });
    close();
  };

  const membershipDate = profile?.created_at
    ? new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(profile.created_at))
    : '—';

  const content = (
      <KeyboardAvoidingView style={x.screen} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View style={[x.header, { paddingTop: embedded ? 10 : Math.max(insets.top, 10), minHeight: embedded ? 76 : 72 + Math.max(insets.top, 10) }] }><Pressable accessibilityRole="button" accessibilityLabel="Profil bilgilerini kapat" onPress={close} style={x.close}><Text style={x.closeText}>‹</Text></Pressable><View><Text style={x.eyebrow}>HESAP BİLGİLERİ</Text><Text style={x.title}>Profili düzenle</Text></View></View>
        {loading ? <View style={x.loading}><ActivityIndicator color={C.green} /><Text style={x.loadingText}>Profil bilgilerin yükleniyor</Text></View> : (
          <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={x.content}>
            {!!loadError && <View style={x.warning}><Text style={x.warningText}>{loadError} Temel bilgiler gösteriliyor.</Text></View>}
            <View style={x.identityCard}><UserAvatar uri={profile?.avatar_url} name={trimmedName || initialName} size={76} /><Text style={x.identityName}>{trimmedName || initialName}</Text><View style={x.verified}><Text style={x.verifiedText}>{profile?.email_verified === false ? 'E-posta doğrulanmadı' : 'E-posta doğrulandı'}</Text></View><View style={x.avatarActions}><Pressable disabled={avatarSaving} onPress={() => void chooseAvatar()} style={x.avatarPrimary}>{avatarSaving ? <ActivityIndicator color={C.dark} /> : <Text style={x.avatarPrimaryText}>{profile?.avatar_url ? 'Fotoğrafı değiştir' : 'Profil fotoğrafı ekle'}</Text>}</Pressable>{profile?.avatar_url && <Pressable disabled={avatarSaving} onPress={() => void removeAvatar()} style={x.avatarRemove}><Text style={x.avatarRemoveText}>Kaldır</Text></Pressable>}</View><Text style={x.avatarPrivacy}>İsteğe bağlıdır. Eklediğinde fotoğrafın profilinde, ilanlarında, mesajlaşmada ve sıralama tercihin açıksa sıralamada görünür; kimlik doğrulaması sayılmaz.</Text></View>
            <View style={x.section}><Text style={x.sectionTitle}>DÜZENLENEBİLİR BİLGİ</Text><Text style={x.label}>Ad ve soyad</Text><TextInput accessibilityLabel="Ad ve soyad" value={name} onChangeText={setName} autoComplete="name" autoCapitalize="words" maxLength={80} placeholder="Adın Soyadın" placeholderTextColor="#98A49D" style={[x.input, name.length > 0 && !nameValid && x.inputInvalid]} /><View style={x.inputMeta}><Text style={[x.hint, name.length > 0 && !nameValid && x.error]}>En az 3 karakter olmalı.</Text><Text style={x.counter}>{name.length}/80</Text></View><Pressable accessibilityRole="button" accessibilityState={{ disabled: !nameValid || !nameChanged || saving }} disabled={!nameValid || !nameChanged || saving} onPress={() => void submit()} style={[x.save, (!nameValid || !nameChanged || saving) && x.saveDisabled]}>{saving ? <ActivityIndicator color={C.white} /> : <Text style={x.saveText}>Değişiklikleri kaydet</Text>}</Pressable></View>
            <View style={x.section}><Text style={x.sectionTitle}>HESAP BİLGİLERİ</Text><InfoRow label="E-posta" value={profile?.email || initialEmail} note="Giriş ve doğrulama adresin" /><InfoRow label="Telefon" value={profile?.phone || 'Eklenmedi'} note="SMS doğrulaması kurulmadan değiştirilemez" /><InfoRow label="Üyelik tarihi" value={membershipDate} /><InfoRow label="Hesap numarası" value={profile ? `#${profile.id}` : '—'} last /></View>
            <View style={x.info}><Text style={x.infoTitle}>E-posta neden buradan değişmiyor?</Text><Text style={x.infoText}>E-posta giriş kimliğindir. Hesabın başkasının eline geçmemesi için e-posta değişikliği ayrıca doğrulama koduyla onaylanmalıdır.</Text></View>
          </ScrollView>
        )}
      </KeyboardAvoidingView>
  );

  if (embedded) return visible ? content : null;
  return <Modal visible={visible} animationType="slide" onRequestClose={close}>{content}</Modal>;
}

function InfoRow({ label, value, note, last = false }: { label: string; value: string; note?: string; last?: boolean }) {
  return <View style={[x.infoRow, last && x.infoRowLast]}><View style={x.infoCopy}><Text style={x.infoLabel}>{label}</Text><Text style={x.infoValue} selectable>{value}</Text>{!!note && <Text style={x.infoNote}>{note}</Text>}</View></View>;
}

const x = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg }, header: { minHeight: 82, paddingTop: 10, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', backgroundColor: C.white, borderBottomWidth: 1, borderBottomColor: C.line }, close: { width: 44, height: 44, borderRadius: 22, backgroundColor: C.bg, alignItems: 'center', justifyContent: 'center', marginRight: 11 }, closeText: { color: C.ink, fontSize: 30, lineHeight: 33 }, eyebrow: { color: C.green, fontSize: 11, letterSpacing: 1.3, fontWeight: '900' }, title: { color: C.ink, fontSize: 21, fontWeight: '900', marginTop: 2 }, loading: { flex: 1, alignItems: 'center', justifyContent: 'center' }, loadingText: { color: C.muted, fontSize: 12, fontWeight: '700', marginTop: 10 }, content: { padding: 18, paddingBottom: 40 }, warning: { padding: 12, borderRadius: 13, backgroundColor: '#FFF4E8', marginBottom: 12 }, warningText: { color: '#8A5A19', fontSize: 12, lineHeight: 18, fontWeight: '700' }, identityCard: { alignItems: 'center', padding: 23, borderRadius: 22, backgroundColor: C.dark }, avatar: { width: 70, height: 70, borderRadius: 35, backgroundColor: C.lime, alignItems: 'center', justifyContent: 'center' }, avatarText: { color: C.dark, fontSize: 27, fontWeight: '900' }, identityName: { color: C.white, fontSize: 19, fontWeight: '900', marginTop: 10 }, verified: { minHeight: 28, paddingHorizontal: 10, borderRadius: 14, backgroundColor: 'rgba(205,234,121,.16)', justifyContent: 'center', marginTop: 7 }, verifiedText: { color: C.lime, fontSize: 12, fontWeight: '900' }, section: { padding: 16, borderRadius: 20, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, marginTop: 13 }, sectionTitle: { color: C.green, fontSize: 11, letterSpacing: 1.2, fontWeight: '900', marginBottom: 14 }, label: { color: C.ink, fontSize: 12, fontWeight: '800', marginBottom: 6 }, input: { minHeight: 50, paddingHorizontal: 14, borderRadius: 14, backgroundColor: '#FAFCF9', borderWidth: 1, borderColor: C.line, color: C.ink, fontSize: 16, fontWeight: '800' }, inputInvalid: { borderColor: '#C76756' }, inputMeta: { flexDirection: 'row', justifyContent: 'space-between', gap: 10, marginTop: 6 }, hint: { color: C.muted, fontSize: 12 }, error: { color: '#A74637' }, counter: { color: C.muted, fontSize: 12 }, save: { minHeight: 50, borderRadius: 15, backgroundColor: C.green, alignItems: 'center', justifyContent: 'center', marginTop: 15 }, saveDisabled: { opacity: .45 }, saveText: { color: C.white, fontSize: 13, fontWeight: '900' }, infoRow: { minHeight: 58, paddingVertical: 11, borderBottomWidth: 1, borderBottomColor: C.line, justifyContent: 'center' }, infoRowLast: { borderBottomWidth: 0, paddingBottom: 2 }, infoCopy: { flex: 1 }, infoLabel: { color: C.muted, fontSize: 11, fontWeight: '800' }, infoValue: { color: C.ink, fontSize: 13, fontWeight: '900', marginTop: 3 }, infoNote: { color: C.muted, fontSize: 12, lineHeight: 18, marginTop: 3 }, info: { padding: 16, borderRadius: 18, backgroundColor: C.soft, marginTop: 13 }, infoTitle: { color: C.dark, fontSize: 12, fontWeight: '900' }, infoText: { color: C.muted, fontSize: 12, lineHeight: 18, marginTop: 5 }, avatarActions: { flexDirection: 'row', gap: 8, marginTop: 14 }, avatarPrimary: { minHeight: 42, borderRadius: 13, backgroundColor: C.lime, paddingHorizontal: 15, alignItems: 'center', justifyContent: 'center' }, avatarPrimaryText: { color: C.dark, fontSize: 12, fontWeight: '900' }, avatarRemove: { minHeight: 42, borderRadius: 13, borderWidth: 1, borderColor: 'rgba(255,255,255,.3)', paddingHorizontal: 14, alignItems: 'center', justifyContent: 'center' }, avatarRemoveText: { color: '#FFE1D9', fontSize: 12, fontWeight: '900' }, avatarPrivacy: { color: '#C9D8D0', fontSize: 11, lineHeight: 16, textAlign: 'center', marginTop: 11, maxWidth: 290 },
});
