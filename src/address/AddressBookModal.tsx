import * as Location from 'expo-location';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import MapView, { MapPressEvent, Marker } from 'react-native-maps';
import { C } from '../../styles';
import { ApiError, apiRequest } from '../lib/api';
import { useNotice } from '../notice/NoticeProvider';
import { DeliveryAddress } from './types';

type AddressResponse = { data: Omit<DeliveryAddress, 'saved'> };
type AddressCollectionResponse = { data: Omit<DeliveryAddress, 'saved'>[] };
type Coordinates = { latitude: number; longitude: number };
type Mode = 'select' | 'manage';

type Props = {
  visible: boolean;
  token: string;
  mode: Mode;
  initialCoordinates?: Coordinates | null;
  onClose: () => void;
  onSelect?: (address: DeliveryAddress) => void;
  embedded?: boolean;
};

type Draft = {
  id?: number;
  label: string;
  publicArea: string;
  fullAddress: string;
  deliveryNotes: string;
  isDefault: boolean;
};

const emptyDraft: Draft = {
  label: '',
  publicArea: '',
  fullAddress: '',
  deliveryNotes: '',
  isDefault: false,
};

const asSaved = (address: Omit<DeliveryAddress, 'saved'>): DeliveryAddress => ({
  ...address,
  saved: true,
});

const uniqueAddressParts = (parts: Array<string | null | undefined>) => {
  const seen = new Set<string>();
  return parts.filter((part): part is string => {
    const value = part?.trim();
    if (!value) return false;
    const key = value.toLocaleLowerCase('tr-TR');
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
};

const locationText = (address: Location.LocationGeocodedAddress) => {
  const streetLine = uniqueAddressParts([address.street, address.streetNumber]).join(' ');
  return uniqueAddressParts([
    streetLine,
    address.district,
    address.subregion,
    address.city,
  ]).join(', ');
};

const publicAreaText = (address: Location.LocationGeocodedAddress) => {
  const neighborhood = address.district;
  const district = address.subregion || address.city;
  const city = address.city || address.region;
  return uniqueAddressParts([neighborhood, district, city]).slice(0, 2).join(', ');
};

export default function AddressBookModal({
  visible,
  token,
  mode,
  initialCoordinates,
  onClose,
  onSelect,
  embedded = false,
}: Props) {
  const { showNotice, confirmNotice } = useNotice();
  const [addresses, setAddresses] = useState<DeliveryAddress[]>([]);
  const [loading, setLoading] = useState(false);
  const [editorOpen, setEditorOpen] = useState(false);
  const mapRef = useRef<MapView>(null);
  const [draft, setDraft] = useState<Draft>(emptyDraft);
  const [coordinates, setCoordinates] = useState<Coordinates | null>(null);
  const [currentCoordinates, setCurrentCoordinates] = useState<Coordinates | null>(initialCoordinates || null);
  const [persist, setPersist] = useState(true);
  const [saving, setSaving] = useState(false);
  const [geocoding, setGeocoding] = useState(false);
  const [locating, setLocating] = useState(false);

  const loadAddresses = useCallback(async () => {
    setLoading(true);
    try {
      const response = await apiRequest<AddressCollectionResponse>('/addresses', { token });
      setAddresses(response.data.map(asSaved));
    } catch (error) {
      showNotice({ tone: 'error', title: 'Adresler alınamadı', message: error instanceof ApiError ? error.message : 'Adres servisine ulaşılamadı.' });
    } finally {
      setLoading(false);
    }
  }, [showNotice, token]);

  useEffect(() => {
    if (!visible) return;
    setEditorOpen(false);
    setDraft(emptyDraft);
    setCoordinates(null);
    setCurrentCoordinates(initialCoordinates || null);
    void loadAddresses();
  }, [initialCoordinates, loadAddresses, visible]);

  const resolveAddress = async (next: Coordinates) => {
    setCoordinates(next);
    setGeocoding(true);
    try {
      const [address] = await Location.reverseGeocodeAsync(next);
      if (address) {
        setDraft(current => ({
          ...current,
          publicArea: publicAreaText(address) || current.publicArea,
          fullAddress: locationText(address) || current.fullAddress,
        }));
      }
    } catch {
      // Adres bulunamazsa kullanıcı açık adresi elle tamamlayabilir.
    } finally {
      setGeocoding(false);
    }
  };

  const startNew = async () => {
    setDraft(emptyDraft);
    setPersist(true);
    setEditorOpen(true);

    let next = initialCoordinates
      || addresses.find(address => address.isDefault)
      || addresses[0]
      || { latitude: 39, longitude: 35 };

    try {
      let permission = await Location.getForegroundPermissionsAsync();
      if (permission.status === 'undetermined') {
        permission = await Location.requestForegroundPermissionsAsync();
      }
      if (permission.status === 'granted') {
        const position = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
        next = { latitude: position.coords.latitude, longitude: position.coords.longitude };
        setCurrentCoordinates(next);
      }
    } catch {}

    await resolveAddress({ latitude: next.latitude, longitude: next.longitude });
  };

  const editAddress = (address: DeliveryAddress) => {
    setDraft({
      id: address.id,
      label: address.label,
      publicArea: address.publicArea,
      fullAddress: address.fullAddress,
      deliveryNotes: address.deliveryNotes || '',
      isDefault: Boolean(address.isDefault),
    });
    setCoordinates({ latitude: address.latitude, longitude: address.longitude });
    setPersist(true);
    setEditorOpen(true);
  };

  const mapPressed = (event: MapPressEvent) => {
    void resolveAddress(event.nativeEvent.coordinate);
  };

  const goToCurrentLocation = async () => {
    setLocating(true);
    try {
      let permission = await Location.getForegroundPermissionsAsync();
      if (permission.status !== 'granted') {
        permission = await Location.requestForegroundPermissionsAsync();
      }
      if (permission.status !== 'granted') {
        showNotice({ tone: 'warning', title: 'Konum izni gerekli', message: 'Mevcut konumunu haritada gösterebilmemiz için telefon ayarlarından konum izni vermelisin.' });
        return;
      }

      const position = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
      const next = { latitude: position.coords.latitude, longitude: position.coords.longitude };
      setCurrentCoordinates(next);
      mapRef.current?.animateToRegion({ ...next, latitudeDelta: .008, longitudeDelta: .008 }, 450);
      await resolveAddress(next);
    } catch {
      showNotice({ tone: 'error', title: 'Konum bulunamadı', message: 'Telefonunun konum servisinin açık olduğundan emin ol ve yeniden dene.' });
    } finally {
      setLocating(false);
    }
  };

  const save = async () => {
    if (!coordinates) {
      showNotice({ tone: 'warning', title: 'Teslimat noktasını seç', message: 'Haritada teslimatın gerçekleşeceği noktaya dokun veya yeşil pini taşı.' });
      return;
    }
    if (draft.label.trim().length < 2) {
      showNotice({ tone: 'warning', title: 'Adres adı gerekli', message: 'Bu adresi daha sonra kolayca bulmak için Ev, İş yeri veya Depo gibi bir ad yaz.' });
      return;
    }
    if (draft.publicArea.trim().length < 2 || draft.fullAddress.trim().length < 10) {
      showNotice({ tone: 'warning', title: 'Adres bilgilerini tamamla', message: 'İlçe/mahalle bilgisini ve teslimatın gerçekleşeceği açık adresi eksiksiz yaz.' });
      return;
    }

    const selection: DeliveryAddress = {
      id: draft.id,
      label: draft.label.trim(),
      publicArea: draft.publicArea.trim(),
      fullAddress: draft.fullAddress.trim(),
      latitude: coordinates.latitude,
      longitude: coordinates.longitude,
      deliveryNotes: draft.deliveryNotes.trim() || null,
      isDefault: draft.isDefault,
      saved: false,
    };

    if (mode === 'select' && !persist && !draft.id) {
      onSelect?.(selection);
      onClose();
      return;
    }

    setSaving(true);
    try {
      const response = await apiRequest<AddressResponse>(
        draft.id ? '/addresses/' + draft.id : '/addresses',
        {
          method: draft.id ? 'PATCH' : 'POST',
          token,
          body: {
            label: selection.label,
            public_area: selection.publicArea,
            full_address: selection.fullAddress,
            latitude: selection.latitude,
            longitude: selection.longitude,
            delivery_notes: selection.deliveryNotes,
            is_default: selection.isDefault,
          },
        },
      );
      const savedAddress = asSaved(response.data);
      await loadAddresses();
      setEditorOpen(false);
      if (mode === 'select') {
        onSelect?.(savedAddress);
        onClose();
      }
    } catch (error) {
      showNotice({ tone: 'error', title: 'Adres kaydedilemedi', message: error instanceof ApiError ? error.message : 'Adres servisine ulaşılamadı.' });
    } finally {
      setSaving(false);
    }
  };

  const removeAddress = async (address: DeliveryAddress) => {
    const confirmed = await confirmNotice({
      tone: 'warning',
      eyebrow: 'ADRES İŞLEMİ',
      title: 'Adresi silmek istiyor musun?',
      message: address.label + ' adresi kayıtlı adreslerinden kalıcı olarak kaldırılacak.',
      primaryLabel: 'Adresi sil',
      secondaryLabel: 'Vazgeç',
    });
    if (!confirmed) return;

    try {
      await apiRequest('/addresses/' + address.id, { method: 'DELETE', token });
      await loadAddresses();
      showNotice({ tone: 'success', title: 'Adres silindi', message: address.label + ' adresi kayıtlı adreslerinden kaldırıldı.' });
    } catch (error) {
      showNotice({ tone: 'error', title: 'Adres silinemedi', message: error instanceof ApiError ? error.message : 'Adres servisine ulaşılamadı.' });
    }
  };

  const choose = (address: DeliveryAddress) => {
    onSelect?.(address);
    onClose();
  };

  const content = (
      <KeyboardAvoidingView style={a.screen} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View style={[a.header, !embedded && a.modalHeader]}>
          <Pressable onPress={editorOpen ? () => setEditorOpen(false) : onClose} style={a.back}>
            <Text style={a.backText}>‹</Text>
          </Pressable>
          <View style={a.headerCopy}>
            <Text style={a.eyebrow}>{mode === 'select' ? 'TESLİMAT KONUMU' : 'HESABIM'}</Text>
            <Text style={a.title}>{editorOpen ? (draft.id ? 'Adresi düzenle' : 'Yeni adres') : 'Kayıtlı adreslerim'}</Text>
          </View>
        </View>

        {editorOpen && coordinates ? (
          <ScrollView contentContainerStyle={a.content} keyboardShouldPersistTaps="handled">
            <Text style={a.help}>Pini teslim alınacak gerçek noktaya taşı veya haritada bir yere dokun.</Text>
            <View style={a.mapWrap}>
              <MapView
                ref={mapRef}
                style={a.map}
                initialRegion={{ ...coordinates, latitudeDelta: .012, longitudeDelta: .012 }}
                onPress={mapPressed}
                showsUserLocation
                showsMyLocationButton
              >
                {!!currentCoordinates && (
                  <Marker coordinate={currentCoordinates} anchor={{ x: .5, y: .5 }} zIndex={1}>
                    <View style={a.currentLocationHalo}><View style={a.currentLocationDot} /></View>
                  </Marker>
                )}
                <Marker
                  coordinate={coordinates}
                  draggable
                  pinColor={C.green}
                  title="Teslimat noktası"
                  zIndex={2}
                  onDragEnd={event => void resolveAddress(event.nativeEvent.coordinate)}
                />
              </MapView>
              <Pressable disabled={locating} onPress={() => void goToCurrentLocation()} style={a.myLocationButton}>
                {locating ? <ActivityIndicator size="small" color={C.green} /> : <Text style={a.myLocationIcon}>⌖</Text>}
                <Text style={a.myLocationText}>Konumuma git</Text>
              </Pressable>
            </View>
            <View style={a.mapLegend}>
              <View style={a.legendBlueDot} /><Text style={a.legendText}>Mavi nokta telefonun</Text>
              <View style={a.legendGreenDot} /><Text style={a.legendText}>Yeşil pin teslimat noktası</Text>
            </View>
            {geocoding && <View style={a.geocode}><ActivityIndicator color={C.green} /><Text style={a.geocodeText}>Adres önerisi hazırlanıyor…</Text></View>}

            <Text style={a.label}>Adres adı</Text>
            <TextInput value={draft.label} onChangeText={label => setDraft(current => ({ ...current, label }))} placeholder="Ev, İş yeri, Depo" style={a.input} />

            <Text style={a.label}>İlanda görünecek bölge</Text>
            <Text style={a.fieldHelp}>Alıcılar tam adres yerine yalnızca bu mahalle/ilçe bilgisini görür.</Text>
            <TextInput value={draft.publicArea} onChangeText={publicArea => setDraft(current => ({ ...current, publicArea }))} placeholder="Örn. Rüstempaşa, Yalova Merkez" style={a.input} />

            <Text style={a.label}>Tam teslimat adresi (gizli)</Text>
            <Text style={a.fieldHelp}>Haritanın oluşturduğu öneriyi kontrol et; sokak ve bina numarasını mutlaka tamamla.</Text>
            <TextInput
              value={draft.fullAddress}
              onChangeText={fullAddress => setDraft(current => ({ ...current, fullAddress }))}
              placeholder="Mahalle, cadde/sokak, bina numarası"
              multiline
              style={[a.input, a.multiline]}
            />

            <Text style={a.label}>Teslimat tarifi (isteğe bağlı)</Text>
            <TextInput
              value={draft.deliveryNotes}
              onChangeText={deliveryNotes => setDraft(current => ({ ...current, deliveryNotes }))}
              placeholder="Güvenlikten teslim edilecek, giriş arka tarafta…"
              multiline
              style={[a.input, a.notes]}
            />

            <Pressable onPress={() => setDraft(current => ({ ...current, isDefault: !current.isDefault }))} style={a.toggleRow}>
              <View style={[a.check, draft.isDefault && a.checkActive]}><Text style={a.checkText}>{draft.isDefault ? '✓' : ''}</Text></View>
              <Text style={a.toggleText}>Varsayılan adresim yap</Text>
            </Pressable>

            {mode === 'select' && !draft.id && (
              <Pressable onPress={() => setPersist(value => !value)} style={a.toggleRow}>
                <View style={[a.check, persist && a.checkActive]}><Text style={a.checkText}>{persist ? '✓' : ''}</Text></View>
                <Text style={a.toggleText}>Sonraki ilanlarım için kaydet</Text>
              </Pressable>
            )}

            <Pressable disabled={saving} onPress={() => void save()} style={[a.primary, saving && a.disabled]}>
              {saving ? <ActivityIndicator color="#FFFFFF" /> : <Text style={a.primaryText}>{mode === 'select' ? 'Bu adresi kullan' : 'Adresi kaydet'}</Text>}
            </Pressable>
          </ScrollView>
        ) : (
          <ScrollView contentContainerStyle={a.content}>
            <Text style={a.help}>Açık adreslerin yalnızca sana gösterilir. İlanda yaklaşık bölge yayınlanır.</Text>
            {loading ? (
              <ActivityIndicator color={C.green} style={a.loader} />
            ) : addresses.length ? (
              addresses.map(address => (
                <View key={address.id} style={a.card}>
                  <Pressable disabled={mode !== 'select'} onPress={() => choose(address)} style={a.cardMain}>
                    <View style={a.pin}><Text style={a.pinText}>⌖</Text></View>
                    <View style={a.cardCopy}>
                      <View style={a.titleRow}>
                        <Text style={a.cardTitle}>{address.label}</Text>
                        {address.isDefault && <Text style={a.defaultBadge}>VARSAYILAN</Text>}
                      </View>
                      <Text style={a.area}>{address.publicArea}</Text>
                      <Text style={a.address} numberOfLines={2}>{address.fullAddress}</Text>
                    </View>
                    {mode === 'select' && <Text style={a.selectArrow}>›</Text>}
                  </Pressable>
                  <View style={a.cardActions}>
                    <Pressable onPress={() => editAddress(address)}><Text style={a.edit}>Düzenle</Text></Pressable>
                    <Pressable onPress={() => removeAddress(address)}><Text style={a.delete}>Sil</Text></Pressable>
                  </View>
                </View>
              ))
            ) : (
              <View style={a.empty}>
                <Text style={a.emptyIcon}>⌖</Text>
                <Text style={a.emptyTitle}>Henüz kayıtlı adresin yok</Text>
                <Text style={a.emptyText}>İlk adresini haritadan seçip açık adresi yazarak oluşturabilirsin.</Text>
              </View>
            )}
            <Pressable onPress={() => void startNew()} style={a.primary}>
              <Text style={a.primaryText}>＋ Haritadan yeni adres seç</Text>
            </Pressable>
          </ScrollView>
        )}
      </KeyboardAvoidingView>
  );

  if (embedded) return visible ? content : null;
  return <Modal visible={visible} animationType="slide" onRequestClose={onClose}>{content}</Modal>;
}

const a = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.bg },
  header: { paddingTop: 12, paddingHorizontal: 18, paddingBottom: 15, flexDirection: 'row', alignItems: 'center', backgroundColor: C.white, borderBottomWidth: 1, borderBottomColor: C.line },
  modalHeader: { paddingTop: Platform.OS === 'ios' ? 58 : 22 },
  back: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center', backgroundColor: C.bg, marginRight: 12 },
  backText: { color: C.ink, fontSize: 34, lineHeight: 36 },
  headerCopy: { flex: 1 },
  eyebrow: { color: C.green, fontSize: 11, letterSpacing: 1.4, fontWeight: '900' },
  title: { color: C.ink, fontSize: 22, fontWeight: '900', marginTop: 2 },
  content: { padding: 20, paddingBottom: 40 },
  help: { color: C.muted, fontSize: 12, lineHeight: 18, marginBottom: 15 },
  loader: { marginVertical: 40 },
  mapWrap: { height: 280, borderRadius: 22, overflow: 'hidden', backgroundColor: '#E7EDE4' },
  map: { position: 'absolute', top: 0, right: 0, bottom: 0, left: 0 },
  myLocationButton: { position: 'absolute', right: 12, bottom: 12, minHeight: 42, borderRadius: 14, backgroundColor: C.white, paddingHorizontal: 12, flexDirection: 'row', alignItems: 'center', gap: 7, shadowColor: '#10261A', shadowOffset: { width: 0, height: 3 }, shadowOpacity: .18, shadowRadius: 7, elevation: 5 },
  myLocationIcon: { color: C.green, fontSize: 20, fontWeight: '900' },
  myLocationText: { color: C.ink, fontSize: 12, fontWeight: '900' },
  mapLegend: { minHeight: 36, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, flexWrap: 'wrap', paddingHorizontal: 4 },
  currentLocationHalo: { width: 30, height: 30, borderRadius: 15, backgroundColor: 'rgba(49,133,246,.22)', alignItems: 'center', justifyContent: 'center' },
  currentLocationDot: { width: 14, height: 14, borderRadius: 7, backgroundColor: '#3185F6', borderWidth: 3, borderColor: C.white },
  legendBlueDot: { width: 9, height: 9, borderRadius: 5, backgroundColor: '#3185F6', borderWidth: 2, borderColor: C.white },
  legendGreenDot: { width: 9, height: 9, borderRadius: 5, backgroundColor: C.green },
  legendText: { color: C.muted, fontSize: 11, marginRight: 7 },
  geocode: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 10 },
  geocodeText: { color: C.muted, fontSize: 12 },
  label: { color: C.ink, fontSize: 12, fontWeight: '900', marginTop: 13, marginBottom: 4 },
  fieldHelp: { color: C.muted, fontSize: 11, lineHeight: 16, marginBottom: 7 },
  input: { minHeight: 50, borderRadius: 15, borderWidth: 1, borderColor: C.line, backgroundColor: C.white, paddingHorizontal: 14, color: C.ink, fontSize: 14, fontWeight: '700' },
  multiline: { minHeight: 92, paddingTop: 13, textAlignVertical: 'top' },
  notes: { minHeight: 76, paddingTop: 13, textAlignVertical: 'top' },
  toggleRow: { flexDirection: 'row', alignItems: 'center', marginTop: 16 },
  check: { width: 25, height: 25, borderRadius: 8, borderWidth: 1.5, borderColor: '#B8C4BC', alignItems: 'center', justifyContent: 'center', marginRight: 10 },
  checkActive: { backgroundColor: C.green, borderColor: C.green },
  checkText: { color: C.white, fontWeight: '900' },
  toggleText: { color: C.ink, fontSize: 12, fontWeight: '800' },
  primary: { minHeight: 55, borderRadius: 17, backgroundColor: C.green, alignItems: 'center', justifyContent: 'center', marginTop: 18, paddingHorizontal: 18 },
  primaryText: { color: C.white, fontSize: 13, fontWeight: '900' },
  disabled: { opacity: .6 },
  card: { backgroundColor: C.white, borderRadius: 18, borderWidth: 1, borderColor: C.line, marginBottom: 12, overflow: 'hidden' },
  cardMain: { padding: 14, flexDirection: 'row', alignItems: 'center' },
  pin: { width: 42, height: 42, borderRadius: 14, backgroundColor: C.soft, alignItems: 'center', justifyContent: 'center', marginRight: 12 },
  pinText: { color: C.green, fontSize: 22, fontWeight: '900' },
  cardCopy: { flex: 1 },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: 7 },
  cardTitle: { color: C.ink, fontSize: 15, fontWeight: '900' },
  defaultBadge: { color: C.green, fontSize: 10, fontWeight: '900', backgroundColor: C.soft, paddingHorizontal: 6, paddingVertical: 4, borderRadius: 7 },
  area: { color: C.green, fontSize: 12, fontWeight: '800', marginTop: 3 },
  address: { color: C.muted, fontSize: 12, lineHeight: 18, marginTop: 3 },
  selectArrow: { color: C.green, fontSize: 28, marginLeft: 7 },
  cardActions: { borderTopWidth: 1, borderTopColor: C.line, paddingHorizontal: 15, paddingVertical: 10, flexDirection: 'row', justifyContent: 'flex-end', gap: 20 },
  edit: { color: C.green, fontSize: 12, fontWeight: '900' },
  delete: { color: '#A23D32', fontSize: 12, fontWeight: '900' },
  empty: { alignItems: 'center', paddingVertical: 38 },
  emptyIcon: { color: C.green, fontSize: 35 },
  emptyTitle: { color: C.ink, fontSize: 17, fontWeight: '900', marginTop: 10 },
  emptyText: { color: C.muted, fontSize: 12, lineHeight: 18, textAlign: 'center', marginTop: 5, maxWidth: 270 },
});