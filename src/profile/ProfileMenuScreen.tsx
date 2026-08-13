import React, { useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { C } from '../../styles';
import UserAvatar from './UserAvatar';
import MonetizedAdSlot from '../advertising/MonetizedAdSlot';

export default function ProfileMenuScreen({ fullName, avatarUrl, email, emailVerified, token, openPublicProfile, openEditProfile, openUsageLimits, openAddresses, openMyListings, openFavorites, openPurchaseHistory, openNotificationPreferences, openBlockedUsers, openTerms, openPrivacy, openDeleteAccount, onSignOut }: {
  fullName: string;
  avatarUrl?: string | null;
  email: string;
  emailVerified: boolean;
  token: string;
  openPublicProfile: () => void;
  openEditProfile: () => void;
  openUsageLimits: () => void;
  openAddresses: () => void;
  openMyListings: () => void;
  openFavorites: () => void;
  openPurchaseHistory: () => void;
  openNotificationPreferences: () => void;
  openBlockedUsers: () => void;
  openTerms: () => void;
  openPrivacy: () => void;
  openDeleteAccount: () => void;
  onSignOut: () => void;
}) {
  const [openingPublicProfile, setOpeningPublicProfile] = useState(false);
  const [adReady, setAdReady] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => setAdReady(true), 450);
    return () => clearTimeout(timer);
  }, []);

  const handleOpenPublicProfile = () => {
    if (openingPublicProfile) return;
    setOpeningPublicProfile(true);
    openPublicProfile();
    setTimeout(() => setOpeningPublicProfile(false), 900);
  };

  return (
    <ScrollView style={x.screen} contentContainerStyle={x.content} showsVerticalScrollIndicator={false}>
      <Text style={x.eyebrow}>HESABIM</Text><Text style={x.title}>Profil</Text>
      <View style={x.profileCard}>
        <UserAvatar uri={avatarUrl} name={fullName} size={72} />
        <Text style={x.name}>{fullName}</Text>
        <Text style={x.email}>{email}</Text>
        <View style={[x.verified, !emailVerified && x.unverified]}><Text style={[x.verifiedText, !emailVerified && x.unverifiedText]}>{emailVerified ? '✓ E-posta doğrulandı' : 'E-posta doğrulanmadı'}</Text></View>
        <Pressable accessibilityRole="button" accessibilityState={{ disabled: openingPublicProfile }} disabled={openingPublicProfile} onPress={handleOpenPublicProfile} style={[x.publicButton, openingPublicProfile && x.publicButtonDisabled]}><Text style={x.publicButtonText}>Profilimi görüntüle</Text><Text style={x.publicButtonArrow}>›</Text></Pressable>
      </View>

      <Text style={x.sectionLabel}>HESAP VE İŞLEMLER</Text>
      <MenuRow label="Profili düzenle" onPress={openEditProfile} />
      <MenuRow label="Limitlerim" onPress={openUsageLimits} />
      <MenuRow label="Kayıtlı adreslerim" onPress={openAddresses} />
      <MenuRow label="İlanlarım" onPress={openMyListings} />
      <MenuRow label="Favorilerim" onPress={openFavorites} />
      <MenuRow label="Alım taleplerim ve işlem geçmişi" onPress={openPurchaseHistory} />
      <MenuRow label="Bildirim tercihleri" onPress={openNotificationPreferences} />
      <MenuRow label="Engellediğim kullanıcılar" onPress={openBlockedUsers} />
      {adReady && <MonetizedAdSlot placement="profile_home" token={token} />}
      <Text style={[x.sectionLabel, { marginTop: 15 }]}>HUKUK VE GİZLİLİK</Text>
      <MenuRow label="Kullanım Şartları" onPress={openTerms} />
      <MenuRow label="Gizlilik Politikası ve KVKK" onPress={openPrivacy} />
      <MenuRow label="Hesabımı sil" danger onPress={openDeleteAccount} />
      <MenuRow label="Çıkış yap" danger onPress={onSignOut} />
    </ScrollView>
  );
}

function MenuRow({ label, onPress, danger = false }: { label: string; onPress: () => void; danger?: boolean }) {
  return <Pressable accessibilityRole="button" accessibilityLabel={label} onPress={onPress} style={x.menuRow}><Text style={[x.menuText, danger && x.danger]}>{label}</Text><Text style={[x.arrow, danger && x.danger]}>›</Text></Pressable>;
}

const x = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg }, content: { padding: 20, paddingBottom: 30 }, eyebrow: { color: C.green, fontSize: 12, letterSpacing: 1.7, fontWeight: '900', marginTop: 8 }, title: { color: C.ink, fontSize: 24, fontWeight: '800', marginTop: 5, marginBottom: 18 },
  profileCard: { alignItems: 'center', padding: 24, borderRadius: 24, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, marginBottom: 20 }, avatar: { width: 72, height: 72, borderRadius: 36, backgroundColor: C.lime, alignItems: 'center', justifyContent: 'center' }, avatarText: { color: C.dark, fontSize: 28, fontWeight: '900' }, name: { color: C.ink, fontSize: 20, fontWeight: '900', marginTop: 11 }, email: { color: C.muted, fontSize: 12, marginTop: 4 }, verified: { minHeight: 28, borderRadius: 14, backgroundColor: C.soft, paddingHorizontal: 10, justifyContent: 'center', marginTop: 8 }, unverified: { backgroundColor: '#FFF1E4' }, verifiedText: { color: C.green, fontSize: 12, fontWeight: '900' }, unverifiedText: { color: '#9A5A20' }, publicButton: { minHeight: 50, alignSelf: 'stretch', borderRadius: 16, backgroundColor: C.dark, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', marginTop: 17 }, publicButtonText: { color: C.white, fontSize: 13, fontWeight: '900' }, publicButtonDisabled: { opacity: .72 }, publicButtonArrow: { position: 'absolute', right: 17, color: C.lime, fontSize: 24 }, sectionLabel: { color: C.green, fontSize: 11, letterSpacing: 1.3, fontWeight: '900', marginBottom: 9, marginLeft: 3 },
  menuRow: { minHeight: 58, paddingHorizontal: 16, borderRadius: 16, backgroundColor: C.white, borderWidth: 1, borderColor: C.line, marginBottom: 8, flexDirection: 'row', alignItems: 'center' }, menuText: { flex: 1, color: C.ink, fontSize: 13, fontWeight: '800' }, arrow: { color: C.muted, fontSize: 23 }, danger: { color: '#A23D32' }, adSlot: { marginTop: 5 },
});
