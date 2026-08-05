import React, { useEffect, useState } from 'react';
import * as SplashScreen from 'expo-splash-screen';
import {
  ActivityIndicator,
  BackHandler,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  SafeAreaView,
  ScrollView,
  StatusBar,
  Text,
  TextInput,
  View,
} from 'react-native';
import MarketplaceApp from '../../MarketplaceApp';
import { CodeRequest, useAuth } from './AuthProvider';
import { authColors as C, authStyles as s } from './authStyles';
import { useNotice } from '../notice/NoticeProvider';
import LegalDocumentScreen from '../legal/LegalDocumentScreen';
import { PRIVACY_NOTICE_VERSION, TERMS_VERSION, LegalDocumentKey } from '../legal/types';

type AuthRoute = 'welcome' | 'login' | 'signup' | 'verify' | 'legal-terms' | 'legal-privacy';
const validEmail = (value: string) => /^\S+@\S+\.\S+$/.test(value.trim());

function PageShell({ children, back }: React.PropsWithChildren<{ back?: () => void }>) {
  return (
    <SafeAreaView style={s.safe}>
      <StatusBar barStyle="dark-content" backgroundColor="#F7F9F6" />
      <KeyboardAvoidingView
        style={s.keyboard}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      >
        <ScrollView
          style={s.scroll}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode={Platform.OS === 'ios' ? 'interactive' : 'on-drag'}
          automaticallyAdjustKeyboardInsets={Platform.OS === 'ios'}
          showsVerticalScrollIndicator={false}
          contentContainerStyle={s.page}
        >
          <View style={s.topRow}>
            {back ? <Pressable onPress={back} style={s.back}><Text style={s.backText}>‹</Text></Pressable> : <View />}
            <Text style={s.brand}>döngü<Text style={s.logoDot}>.</Text></Text>
          </View>
          {children}
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

function AuthHero({ step, title, text, icon }: { step: string; title: string; text: string; icon: string }) {
  return (
    <View style={s.authHero}>
      <View style={s.authHeroGlowLarge} />
      <View style={s.authHeroGlowSmall} />
      <View style={s.authHeroTop}>
        <View style={s.authStepPill}><Text style={s.authStepText}>{step}</Text></View>
        <View style={s.authHeroIcon}><Text style={s.authHeroIconText}>{icon}</Text></View>
      </View>
      <Text style={s.authHeroTitle}>{title}</Text>
      <Text style={s.authHeroText}>{text}</Text>
    </View>
  );
}
function Welcome({ navigate, explore, openLegal }: { navigate: (route: AuthRoute) => void; explore: () => void; openLegal: (document: LegalDocumentKey) => void }) {
  return (
    <PageShell>
      <View style={s.hero}>
        <View style={s.heroBadge}><Text style={s.heroBadgeText}>YAKININDAKİ DÖNGÜ</Text></View>
        <Text style={s.heroTitle}>Ambalajlar boş durmasın, değere dönüşsün.</Text>
        <Text style={s.heroText}>Yakınındaki ilanları keşfet, güvenle teslim al ve DOA iade noktalarında değerlendir.</Text>
        <Text style={[s.heroBadgeText, { marginTop: 22, opacity: .86 }]}>KABUL EDİLEN MALZEMELER</Text>
        <View style={s.stats}>
          <View style={s.stat}><Text style={s.statValue}>PET</Text><Text style={s.statLabel}>AMBALAJ</Text></View>
          <View style={s.stat}><Text style={s.statValue}>CAM</Text><Text style={s.statLabel}>ŞİŞE</Text></View>
          <View style={s.stat}><Text style={s.statValue}>ALÜ.</Text><Text style={s.statLabel}>KUTU</Text></View>
        </View>
      </View>
      <View style={s.content}>
        <Pressable onPress={explore} style={({ pressed }) => [s.primary, pressed && s.primaryPressed]}>
          <Text style={s.primaryText}>Yakındaki ilanları keşfet</Text>
        </Pressable>
        <Pressable onPress={() => navigate('signup')} style={s.secondary}>
          <Text style={s.secondaryText}>Ücretsiz hesap oluştur</Text>
        </Pressable>
        <View style={s.footerRow}>
          <Text style={s.footerText}>Zaten hesabın var mı? </Text>
          <Pressable onPress={() => navigate('login')}><Text style={s.footerLink}>Giriş yap</Text></Pressable>
        </View>
        <View style={[s.footerRow, { marginTop: 14, gap: 15 }]}>
          <Pressable accessibilityRole="link" onPress={() => openLegal('terms')}><Text style={s.footerLink}>Kullanım Şartları</Text></Pressable>
          <Pressable accessibilityRole="link" onPress={() => openLegal('privacy')}><Text style={s.footerLink}>Gizlilik Politikası</Text></Pressable>
        </View>
      </View>
    </PageShell>
  );
}

function Login({ back, goSignup, continueWith }: { back: () => void; goSignup: () => void; continueWith: (request: CodeRequest) => Promise<string | undefined> }) {
  const [email, setEmail] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const submit = async () => {
    if (!validEmail(email)) return setError('Geçerli bir e-posta adresi girmelisin.');
    setSubmitting(true); setError('');
    const submitError = await continueWith({ intent: 'login', email });
    if (submitError) setError(submitError);
    setSubmitting(false);
  };
  return (
    <PageShell back={back}>
      <AuthHero
        step="GÜVENLİ GİRİŞ"
        title={'Kaldığın yerden\ndöngüye devam et.'}
        text="Şifre ezberlemeden, yalnızca e-postana gelen tek kullanımlık kodla hesabına güvenle ulaş."
        icon="→"
      />
      <View style={s.authPanel}>
        <Text style={s.panelEyebrow}>HESABINA DEVAM ET</Text>
        <Text style={s.panelTitle}>E-posta adresin</Text>
        <Text style={s.panelText}>Sana 10 dakika geçerli, 6 haneli bir giriş kodu göndereceğiz.</Text>
        <View style={s.panelForm}>
          <View style={s.field}>
            <Text style={s.label}>E-posta</Text>
            <TextInput value={email} onChangeText={setEmail} autoCapitalize="none" autoComplete="email" keyboardType="email-address" placeholder="ornek@email.com" placeholderTextColor="#98A49D" style={[s.input, !!error && s.inputError]} />
          </View>
          {!!error && <Text style={s.error}>{error}</Text>}
          <Pressable onPress={submit} disabled={submitting} style={({ pressed }) => [s.primary, pressed && s.primaryPressed, submitting && s.primaryDisabled]}>
            {submitting ? <ActivityIndicator color={C.white} /> : <Text style={s.primaryText}>Giriş kodu gönder</Text>}
          </Pressable>
        </View>
        <View style={s.footerRow}><Text style={s.footerText}>Henüz hesabın yok mu? </Text><Pressable onPress={goSignup}><Text style={s.footerLink}>Hesap oluştur</Text></Pressable></View>
      </View>
    </PageShell>
  );
}
function Signup({ back, goLogin, openLegal, continueWith }: { back: () => void; goLogin: () => void; openLegal: (document: LegalDocumentKey) => void; continueWith: (request: CodeRequest) => Promise<string | undefined> }) {
  const [fullName, setFullName] = useState('');
  const [email, setEmail] = useState('');
  const [terms, setTerms] = useState(false);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const submit = async () => {
    if (fullName.trim().length < 3) return setError('Ad ve soyad en az 3 karakter olmalı.');
    if (!validEmail(email)) return setError('Geçerli bir e-posta adresi girmelisin.');
    if (!terms) return setError('Devam etmek için kullanım şartlarını ve gizlilik politikasını kabul etmelisin.');
    setSubmitting(true); setError('');
    const submitError = await continueWith({ intent: 'register', email, name: fullName, termsAccepted: terms, termsVersion: TERMS_VERSION, privacyNoticeVersion: PRIVACY_NOTICE_VERSION });
    if (submitError) setError(submitError);
    setSubmitting(false);
  };
  return (
    <PageShell back={back}>
      <AuthHero
        step="ÜCRETSİZ ÜYELİK"
        title={'Döngüye katıl,\nkatkını görünür kıl.'}
        text="Ambalajlarını paylaş, güvenli teslimatlar yap ve doğaya sağladığın katkıyı biriktir."
        icon="＋"
      />
      <View style={s.authPanel}>
        <Text style={s.panelEyebrow}>YENİ HESAP</Text>
        <Text style={s.panelTitle}>Seni tanıyalım</Text>
        <Text style={s.panelText}>Kayıt birkaç adım sürer; şifre oluşturman gerekmez.</Text>
        <View style={s.panelForm}>
          <View style={s.field}><Text style={s.label}>Ad ve soyad</Text><TextInput value={fullName} onChangeText={setFullName} autoComplete="name" placeholder="Adın Soyadın" placeholderTextColor="#98A49D" style={s.input} /></View>
          <View style={s.field}><Text style={s.label}>E-posta</Text><TextInput value={email} onChangeText={setEmail} autoCapitalize="none" autoComplete="email" keyboardType="email-address" placeholder="ornek@email.com" placeholderTextColor="#98A49D" style={s.input} /></View>
          <View style={s.checkRow}>
            <Pressable accessibilityRole="checkbox" accessibilityLabel="Kullanım şartlarını kabul et" accessibilityState={{ checked: terms }} onPress={() => setTerms(current => !current)}>
              <View style={[s.checkbox, terms && s.checkboxActive]}><Text style={s.checkmark}>{terms ? '✓' : ''}</Text></View>
            </Pressable>
            <Text style={s.terms}><Text accessibilityRole="link" onPress={() => openLegal('terms')} style={s.inlineLink}>Kullanım Şartları</Text><Text>’nı kabul ediyor, </Text><Text accessibilityRole="link" onPress={() => openLegal('privacy')} style={s.inlineLink}>Gizlilik Politikası ve KVKK Aydınlatma Metni</Text><Text>’ni okuduğumu teyit ediyorum.</Text></Text>
          </View>
          {!!error && <Text style={s.error}>{error}</Text>}
          <Pressable onPress={submit} disabled={submitting} style={({ pressed }) => [s.primary, pressed && s.primaryPressed, submitting && s.primaryDisabled]}>
            {submitting ? <ActivityIndicator color={C.white} /> : <Text style={s.primaryText}>Doğrulama kodu gönder</Text>}
          </Pressable>
        </View>
        <View style={s.footerRow}><Text style={s.footerText}>Zaten hesabın var mı? </Text><Pressable onPress={goLogin}><Text style={s.footerLink}>Giriş yap</Text></Pressable></View>
      </View>
    </PageShell>
  );
}
function Verify({ request, back, resend }: { request: CodeRequest; back: () => void; resend: () => Promise<string | undefined> }) {
  const { verifyCode } = useAuth();
  const { showNotice } = useNotice();
  const [code, setCode] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const submit = async () => {
    if (!/^\d{6}$/.test(code)) return setError('E-postana gelen 6 haneli kodu girmelisin.');
    setSubmitting(true); setError(''); setMessage('');
    const result = await verifyCode(request.email, code);
    if (result.error) {
      if (result.error.includes('5 kez hatalı') || result.error.includes('çok fazla')) {
        showNotice({ tone: 'warning', title: 'Doğrulama sınırına ulaştın', message: result.error });
      } else {
        setError(result.error);
      }
    }
    setSubmitting(false);
  };
  const sendAgain = async () => {
    setSubmitting(true); setError('');
    const resendError = await resend();
    if (resendError) {
      if (resendError.includes('çok fazla')) {
        showNotice({ tone: 'warning', title: 'Yeni kod için biraz bekle', message: resendError });
      } else {
        setError(resendError);
      }
    } else setMessage('Yeni kod gönderildi. Önceki kod artık geçersiz.');
    setSubmitting(false);
  };
  return (
    <PageShell back={back}>
      <AuthHero
        step="SON ADIM"
        title={'E-postanı doğrula,\ndöngüye güvenle katıl.'}
        text="Tek kullanımlık kod, hesabına yalnızca senin erişebilmeni sağlar."
        icon="✓"
      />
      <View style={s.authPanel}>
        <View style={s.recipientCard}>
          <View style={s.recipientIcon}><Text style={s.recipientIconText}>@</Text></View>
          <View style={s.recipientCopy}>
            <Text style={s.recipientLabel}>KODUN GÖNDERİLDİĞİ ADRES</Text>
            <Text style={s.recipientEmail} numberOfLines={1}>{request.email.trim().toLowerCase()}</Text>
          </View>
        </View>
        <Text style={s.panelEyebrow}>DOĞRULAMA KODU</Text>
        <Text style={s.panelTitle}>6 haneli kodu gir</Text>
        <Text style={s.panelText}>Kod 10 dakika geçerlidir ve yalnızca bir kez kullanılabilir.</Text>
        <View style={s.panelForm}>
          <TextInput value={code} onChangeText={value => setCode(value.replace(/\D/g, '').slice(0, 6))} keyboardType="number-pad" textContentType="oneTimeCode" maxLength={6} accessibilityLabel="6 haneli doğrulama kodu" placeholder="000000" placeholderTextColor="#B8C2BC" style={[s.input, s.codeInput, !!error && s.inputError]} />
          {!!error && <Text style={s.error}>{error}</Text>}
          {!!message && <View style={s.info}><Text style={s.infoText}>{message}</Text></View>}
          <Pressable onPress={submit} disabled={submitting} style={({ pressed }) => [s.primary, pressed && s.primaryPressed, submitting && s.primaryDisabled]}>
            {submitting ? <ActivityIndicator color={C.white} /> : <Text style={s.primaryText}>{request.intent === 'register' ? 'Hesabı oluştur' : 'Giriş yap'}</Text>}
          </Pressable>
          <Pressable onPress={sendAgain} disabled={submitting} style={[s.secondary, submitting && s.primaryDisabled]}><Text style={s.secondaryText}>Kodu yeniden gönder</Text></Pressable>
        </View>
      </View>
    </PageShell>
  );
}
function ProfileCompletion({ onSignOut }: { onSignOut: () => Promise<void> }) {
  const { user, completeProfile } = useAuth();
  const [fullName, setFullName] = useState(user?.fullName ?? '');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const submit = async () => {
    if (fullName.trim().length < 3) return setError('Ad ve soyad en az 3 karakter olmalı.');
    setSubmitting(true);
    const result = await completeProfile(fullName);
    if (result.error) setError(result.error);
    setSubmitting(false);
  };
  return (
    <PageShell>
      <View style={s.content}>
        <Text style={s.eyebrow}>SON BİR ADIM</Text><Text style={s.title}>Profilini tamamla</Text>
        <Text style={s.subtitle}>Diğer kullanıcıların seni tanıyabilmesi için görünen adını doğrula.</Text>
        <View style={s.form}>
          <View style={s.field}><Text style={s.label}>Ad ve soyad</Text><TextInput value={fullName} onChangeText={setFullName} autoComplete="name" placeholder="Adın Soyadın" placeholderTextColor="#98A49D" style={s.input} /></View>
          {!!error && <Text style={s.error}>{error}</Text>}
          <Pressable onPress={submit} disabled={submitting} style={[s.primary, submitting && s.primaryDisabled]}>{submitting ? <ActivityIndicator color={C.white} /> : <Text style={s.primaryText}>Profili tamamla</Text>}</Pressable>
          <Pressable onPress={() => void onSignOut()} style={s.secondary}><Text style={s.secondaryText}>Çıkış yap</Text></Pressable>
        </View>
      </View>
    </PageShell>
  );
}

export default function AuthGate() {
  const { loading, user, token, signOut, deleteAccount, requestCode, updateProfile, refreshSession } = useAuth();
  const { showNotice } = useNotice();
  const [route, setRoute] = useState<AuthRoute>('welcome');
  const [legalBackRoute, setLegalBackRoute] = useState<'welcome' | 'signup'>('welcome');
  const [pending, setPending] = useState<CodeRequest | null>(null);
  const [guest, setGuest] = useState(false);

  useEffect(() => {
    if (!loading) SplashScreen.hideAsync().catch(() => {});
  }, [loading]);

  useEffect(() => {
    if (Platform.OS !== 'android' || loading || user || guest) return;
    const subscription = BackHandler.addEventListener('hardwareBackPress', () => {
      if (route === 'legal-terms' || route === 'legal-privacy') {
        setRoute(legalBackRoute);
        return true;
      }
      if (route === 'verify') {
        setRoute(pending?.intent === 'login' ? 'login' : 'signup');
        return true;
      }
      if (route === 'login' || route === 'signup') {
        setRoute('welcome');
        return true;
      }
      return false;
    });
    return () => subscription.remove();
  }, [guest, legalBackRoute, loading, pending?.intent, route, user]);

  const handleSignOut = async () => {
    await signOut();
    setPending(null);
    setGuest(false);
    setRoute('welcome');
  };
  const openLegal = (document: LegalDocumentKey, from: 'welcome' | 'signup') => {
    setLegalBackRoute(from);
    setRoute(document === 'terms' ? 'legal-terms' : 'legal-privacy');
  };
  const continueWith = async (request: CodeRequest): Promise<string | undefined> => {
    const result = await requestCode(request);
    if (result.error) {
      if (result.error.includes('çok fazla')) {
        showNotice({ tone: 'warning', title: 'Yeni kod için biraz bekle', message: result.error });
        return undefined;
      }
      return result.error;
    }
    setPending(request);
    setRoute('verify');
    return undefined;
  };

  if (loading) return <View style={s.loading} />;
  if (user?.profileComplete) return <MarketplaceApp fullName={user.fullName} userEmail={user.email} userEmailVerified={user.emailVerified} userId={user.id} token={token} avatarUrl={user.avatarUrl} onProfileUpdated={updateProfile} onProfileRefresh={refreshSession} onSignOut={handleSignOut} onDeleteAccount={deleteAccount} />;
  if (user) return <ProfileCompletion onSignOut={handleSignOut} />;
  if (guest) return <MarketplaceApp fullName="Misafir" isGuest onSignOut={() => setGuest(false)} onRequireAuth={() => { setGuest(false); setRoute('signup'); }} />;
  if (route === 'legal-terms' || route === 'legal-privacy') return <LegalDocumentScreen documentKey={route === 'legal-terms' ? 'terms' : 'privacy'} back={() => setRoute(legalBackRoute)} />;
  if (route === 'login') return <Login back={() => setRoute('welcome')} goSignup={() => setRoute('signup')} continueWith={continueWith} />;
  if (route === 'signup') return <Signup back={() => setRoute('welcome')} goLogin={() => setRoute('login')} openLegal={document => openLegal(document, 'signup')} continueWith={continueWith} />;
  if (route === 'verify' && pending) return <Verify request={pending} back={() => setRoute(pending.intent === 'login' ? 'login' : 'signup')} resend={async () => (await requestCode(pending)).error} />;
  return <Welcome navigate={setRoute} explore={() => setGuest(true)} openLegal={document => openLegal(document, 'welcome')} />;
}


