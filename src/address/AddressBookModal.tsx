import * as Location from 'expo-location';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
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
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { C } from '../../styles';
import { ApiError, apiRequest } from '../lib/api';
import { useNotice } from '../notice/NoticeProvider';
import { DeliveryAddress } from './types';

type AddressResponse = { data: Omit<DeliveryAddress, 'saved'> };
type AddressCollectionResponse = { data: Omit<DeliveryAddress, 'saved'>[] };
type RegionOption = { id: number; name: string };
type RegionCollectionResponse = { data: RegionOption[] };
type Coordinates = { latitude: number; longitude: number };
type Mode = 'select' | 'manage';
type PickerType = 'province' | 'district';

type Props = {
  visible: boolean;
  token: string;
  mode: Mode;
  initialCoordinates?: Coordinates | null;
  onClose: () => void;
  onSelect?: (address: DeliveryAddress) => void;
  onDeleted?: (addressId: number) => void;
  selectedAddressId?: number | null;
  embedded?: boolean;
};

type Draft = {
  id?: number;
  label: string;
  provinceId: number | null;
  provinceName: string;
  districtId: number | null;
  districtName: string;
  neighborhood: string;
  fullAddress: string;
  deliveryNotes: string;
  isDefault: boolean;
  activeListingsCount: number;
};

const emptyDraft: Draft = {
  label: '',
  provinceId: null,
  provinceName: '',
  districtId: null,
  districtName: '',
  neighborhood: '',
  fullAddress: '',
  deliveryNotes: '',
  isDefault: false,
  activeListingsCount: 0,
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

const normalizeRegion = (value?: string | null) => (value || '')
  .trim()
  .toLocaleLowerCase('tr-TR')
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '');

const publicArea = (draft: Draft) => uniqueAddressParts([
  draft.neighborhood,
  draft.districtName,
  draft.provinceName,
]).join(', ');

export default function AddressBookModal({
  visible,
  token,
  mode,
  onClose,
  onSelect,
  onDeleted,
  selectedAddressId,
  embedded = false,
}: Props) {
  const insets = useSafeAreaInsets();
  const { showNotice, confirmNotice } = useNotice();
  const [addresses, setAddresses] = useState<DeliveryAddress[]>([]);
  const [provinces, setProvinces] = useState<RegionOption[]>([]);
  const [districts, setDistricts] = useState<RegionOption[]>([]);
  const [loading, setLoading] = useState(false);
  const [regionsLoading, setRegionsLoading] = useState(false);
  const [editorOpen, setEditorOpen] = useState(false);
  const [draft, setDraft] = useState<Draft>(emptyDraft);
  const [coordinates, setCoordinates] = useState<Coordinates | null>(null);
  const [saving, setSaving] = useState(false);
  const [geocoding, setGeocoding] = useState(false);
  const [locating, setLocating] = useState(false);
  const [picker, setPicker] = useState<PickerType | null>(null);
  const [pickerSearch, setPickerSearch] = useState('');

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
  const loadProvinces = useCallback(async (): Promise<RegionOption[]> => {
    try {
      const response = await apiRequest<RegionCollectionResponse>('/regions/provinces');
      setProvinces(response.data);
      return response.data;
    } catch (error) {
      showNotice({ tone: 'error', title: 'İller alınamadı', message: error instanceof ApiError ? error.message : 'Bölge servisine ulaşılamadı.' });
      return [];
    }
  }, [showNotice]);

  const loadDistricts = useCallback(async (provinceId: number): Promise<RegionOption[]> => {
    setRegionsLoading(true);
    try {
      const response = await apiRequest<RegionCollectionResponse>('/regions/provinces/' + provinceId + '/districts');
      setDistricts(response.data);
      return response.data;
    } catch (error) {
      showNotice({ tone: 'error', title: 'İlçeler alınamadı', message: error instanceof ApiError ? error.message : 'Bölge servisine ulaşılamadı.' });
      setDistricts([]);
      return [];
    } finally {
      setRegionsLoading(false);
    }
  }, [showNotice]);

  useEffect(() => {
    if (!visible) return;
    setEditorOpen(false);
    setDraft(emptyDraft);
    setCoordinates(null);
    setDistricts([]);
    setPicker(null);
    setPickerSearch('');
    void Promise.all([loadAddresses(), loadProvinces()]);
  }, [loadAddresses, loadProvinces, visible]);

  const resolveAddress = async (next: Coordinates) => {
    setCoordinates(next);
    setGeocoding(true);
    try {
      const [address] = await Location.reverseGeocodeAsync(next);
      if (!address) return;

      const availableProvinces = provinces.length ? provinces : await loadProvinces();
      const provinceCandidates = [address.region, address.city].map(normalizeRegion).filter(Boolean);
      const province = availableProvinces.find(item => provinceCandidates.includes(normalizeRegion(item.name)));
      const availableDistricts = province ? await loadDistricts(province.id) : [];
      const districtCandidates = [address.subregion, address.city, address.district].map(normalizeRegion).filter(Boolean);
      const district = availableDistricts.find(item => districtCandidates.includes(normalizeRegion(item.name)));
      const neighborhoodCandidate = address.district && normalizeRegion(address.district) !== normalizeRegion(district?.name)
        ? address.district
        : address.name || '';

      setDraft(current => ({
        ...current,
        provinceId: province?.id || current.provinceId,
        provinceName: province?.name || current.provinceName,
        districtId: district?.id || current.districtId,
        districtName: district?.name || current.districtName,
        neighborhood: neighborhoodCandidate || current.neighborhood,
        fullAddress: locationText(address) || current.fullAddress,
      }));
    } catch {
      showNotice({ tone: 'warning', title: 'Adres bilgisi tamamlanamadı', message: 'Konum alındı. İl, ilçe, mahalle ve tam adres alanlarını kontrol ederek tamamla.' });
    } finally {
      setGeocoding(false);
    }
  };

  const startNew = () => {
    setDraft(emptyDraft);
    setCoordinates(null);
    setDistricts([]);
    setEditorOpen(true);
  };

  const editAddress = async (address: DeliveryAddress) => {
    const areaParts = address.publicArea.split(',').map(part => part.trim()).filter(Boolean);
    const provinceName = address.provinceName || areaParts[areaParts.length - 1] || '';
    const districtName = address.districtName || areaParts[areaParts.length - 2] || '';
    const neighborhood = address.neighborhood || areaParts[0] || '';
    const availableProvinces = provinces.length ? provinces : await loadProvinces();
    const province = address.provinceId
      ? availableProvinces.find(item => item.id === address.provinceId)
      : availableProvinces.find(item => normalizeRegion(item.name) === normalizeRegion(provinceName));
    const availableDistricts = province ? await loadDistricts(province.id) : [];
    const district = address.districtId
      ? availableDistricts.find(item => item.id === address.districtId)
      : availableDistricts.find(item => normalizeRegion(item.name) === normalizeRegion(districtName));

    setDraft({
      id: address.id,
      label: address.label,
      provinceId: province?.id || null,
      provinceName: province?.name || provinceName,
      districtId: district?.id || null,
      districtName: district?.name || districtName,
      neighborhood,
      fullAddress: address.fullAddress,
      deliveryNotes: address.deliveryNotes || '',
      isDefault: Boolean(address.isDefault),
      activeListingsCount: address.activeListingsCount || 0,
    });
    setCoordinates({ latitude: address.latitude, longitude: address.longitude });
    setEditorOpen(true);
  };

  const goToCurrentLocation = async () => {
    setLocating(true);
    try {
      let permission = await Location.getForegroundPermissionsAsync();
      if (permission.status !== 'granted') {
        permission = await Location.requestForegroundPermissionsAsync();
      }
      if (permission.status !== 'granted') {
        showNotice({ tone: 'warning', title: 'Konum izni gerekli', message: 'Mevcut konumunu teslimat adresi olarak kullanabilmemiz için telefon ayarlarından konum izni vermelisin.' });
        return;
      }

      const position = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
      const next = { latitude: position.coords.latitude, longitude: position.coords.longitude };
      await resolveAddress(next);
    } catch {
      showNotice({ tone: 'error', title: 'Konum bulunamadı', message: 'Telefonunun konum servisinin açık olduğundan emin ol ve yeniden dene.' });
    } finally {
      setLocating(false);
    }
  };

  const geocodeDraft = async (): Promise<Coordinates | null> => {
    try {
      let permission = await Location.getForegroundPermissionsAsync();
      if (permission.status !== 'granted') {
        permission = await Location.requestForegroundPermissionsAsync();
      }
      if (permission.status !== 'granted') {
        showNotice({
          tone: 'warning',
          title: 'Konum izni gerekli',
          message: 'Yazdığın adresi yakındaki ilan ve kilometre hesabında kullanabilmemiz için konum izni vermelisin.',
        });
        return null;
      }

      setGeocoding(true);
      const query = [
        draft.fullAddress.trim(),
        draft.neighborhood.trim(),
        draft.districtName,
        draft.provinceName,
        'Türkiye',
      ].filter(Boolean).join(', ');
      const [result] = await Location.geocodeAsync(query);
      if (!result) {
        showNotice({
          tone: 'warning',
          title: 'Adres doğrulanamadı',
          message: 'Mahalle, ilçe, il, sokak ve bina numarası bilgilerini kontrol edip yeniden dene.',
        });
        return null;
      }

      const next = { latitude: result.latitude, longitude: result.longitude };
      setCoordinates(next);
      return next;
    } catch {
      showNotice({
        tone: 'error',
        title: 'Adres doğrulanamadı',
        message: 'Adres konumu belirlenemedi. İnternet bağlantını ve adres bilgilerini kontrol edip yeniden dene.',
      });
      return null;
    } finally {
      setGeocoding(false);
    }
  };
  const save = async () => {
    if (draft.label.trim().length < 2) {
      showNotice({ tone: 'warning', title: 'Adres adı gerekli', message: 'Bu adresi daha sonra kolayca bulmak için Ev, İş yeri veya Depo gibi bir ad yaz.' });
      return;
    }
    if (!draft.provinceId) {
      showNotice({ tone: 'warning', title: 'İl seçmelisin', message: 'Teslimat adresinin bulunduğu ili seç.' });
      return;
    }
    if (!draft.districtId) {
      showNotice({ tone: 'warning', title: 'İlçe seçmelisin', message: 'Seçtiğin ile bağlı ilçelerden birini seç.' });
      return;
    }
    if (draft.neighborhood.trim().length < 2) {
      showNotice({ tone: 'warning', title: 'Mahalle gerekli', message: 'Teslimat adresinin bulunduğu mahalleyi yaz.' });
      return;
    }
    if (draft.fullAddress.trim().length < 10) {
      showNotice({ tone: 'warning', title: 'Tam adres gerekli', message: 'Cadde veya sokak ile bina ve daire bilgilerini içeren tam teslimat adresini yaz.' });
      return;
    }

    setSaving(true);
    try {
      const resolvedCoordinates = coordinates || await geocodeDraft();
      if (!resolvedCoordinates) return;

      const selection: DeliveryAddress = {
        id: draft.id,
        label: draft.label.trim(),
        provinceId: draft.provinceId,
        provinceName: draft.provinceName,
        districtId: draft.districtId,
        districtName: draft.districtName,
        neighborhood: draft.neighborhood.trim(),
        publicArea: publicArea(draft),
        fullAddress: draft.fullAddress.trim(),
        latitude: resolvedCoordinates.latitude,
        longitude: resolvedCoordinates.longitude,
        deliveryNotes: draft.deliveryNotes.trim() || null,
        isDefault: draft.isDefault,
        saved: false,
      };


      const response = await apiRequest<AddressResponse>(
        draft.id ? '/addresses/' + draft.id : '/addresses',
        {
          method: draft.id ? 'PATCH' : 'POST',
          token,
          body: {
            label: selection.label,
            province_id: selection.provinceId,
            district_id: selection.districtId,
            neighborhood: selection.neighborhood,
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
      message: address.activeListingsCount
        ? address.label + ' kayıtlı adreslerinden silinecek. Bu adresle oluşturduğun ' + address.activeListingsCount + ' yayındaki ilanın teslimat bilgileri değişmeden korunacak.'
        : address.label + ' kayıtlı adreslerinden silinecek. Daha önce bu adresle oluşturduğun ilanların teslimat bilgileri değişmeden korunur.',
      primaryLabel: 'Adresi sil',
      secondaryLabel: 'Vazgeç',
    });
    if (!confirmed) return;

    try {
      await apiRequest('/addresses/' + address.id, { method: 'DELETE', token });
      if (address.id) onDeleted?.(address.id);
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
  const openDistrictPicker = () => {
    if (!draft.provinceId) {
      showNotice({ tone: 'info', title: 'Önce il seçmelisin', message: 'İlçe listesini görebilmek için önce teslimat adresinin bulunduğu ili seç.' });
      return;
    }
    setPickerSearch('');
    setPicker('district');
  };

  const selectProvince = async (province: RegionOption) => {
    setDraft(current => ({
      ...current,
      provinceId: province.id,
      provinceName: province.name,
      districtId: null,
      districtName: '',
    }));
    setCoordinates(null);
    setPicker(null);
    setPickerSearch('');
    await loadDistricts(province.id);
  };

  const selectDistrict = (district: RegionOption) => {
    setDraft(current => ({ ...current, districtId: district.id, districtName: district.name }));
    setCoordinates(null);
    setPicker(null);
    setPickerSearch('');
  };

  const pickerOptions = picker === 'province' ? provinces : districts;
  const filteredPickerOptions = useMemo(() => {
    const search = normalizeRegion(pickerSearch);
    return search
      ? pickerOptions.filter(item => normalizeRegion(item.name).includes(search))
      : pickerOptions;
  }, [pickerOptions, pickerSearch]);

  const contentBottom = Math.max(insets.bottom, 18) + 36;
  const actionLabel = draft.id ? 'Değişiklikleri kaydet' : 'Teslimat adresi oluştur';
  const regionPicker = (
    <Modal visible={picker !== null} transparent animationType="fade" onRequestClose={() => setPicker(null)}>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={a.pickerBackdrop}>
        <Pressable style={StyleSheet.absoluteFill} onPress={() => setPicker(null)} />
        <View style={[a.pickerSheet, { paddingBottom: Math.max(insets.bottom, 16) }]}>
          <View style={a.pickerHandle} />
          <View style={a.pickerHeader}>
            <View>
              <Text style={a.eyebrow}>TESLİMAT ADRESİ</Text>
              <Text style={a.pickerTitle}>{picker === 'province' ? 'İl seç' : 'İlçe seç'}</Text>
            </View>
            <Pressable onPress={() => setPicker(null)} style={a.pickerClose}><Text style={a.pickerCloseText}>×</Text></Pressable>
          </View>
          <TextInput
            value={pickerSearch}
            onChangeText={setPickerSearch}
            placeholder={picker === 'province' ? 'İl ara' : 'İlçe ara'}
            style={a.searchInput}
            autoFocus
          />
          <ScrollView keyboardShouldPersistTaps="handled" style={a.pickerList}>
            {regionsLoading && picker === 'district' ? (
              <ActivityIndicator color={C.green} style={a.pickerLoader} />
            ) : filteredPickerOptions.length ? (
              filteredPickerOptions.map(option => {
                const selected = picker === 'province' ? draft.provinceId === option.id : draft.districtId === option.id;
                return (
                  <Pressable
                    key={option.id}
                    onPress={() => picker === 'province' ? void selectProvince(option) : selectDistrict(option)}
                    style={[a.pickerOption, selected && a.pickerOptionSelected]}
                  >
                    <Text style={[a.pickerOptionText, selected && a.pickerOptionTextSelected]}>{option.name}</Text>
                    {selected && <Text style={a.pickerCheck}>✓</Text>}
                  </Pressable>
                );
              })
            ) : (
              <Text style={a.pickerEmpty}>Aramana uygun sonuç bulunamadı.</Text>
            )}
          </ScrollView>
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );

  const content = (
      <KeyboardAvoidingView style={a.screen} behavior={Platform.OS === 'ios' ? 'padding' : 'height'} keyboardVerticalOffset={0}>
        <View style={[a.header, !embedded && a.modalHeader]}>
          <Pressable onPress={editorOpen ? () => setEditorOpen(false) : onClose} style={a.back}>
            <Text style={a.backText}>‹</Text>
          </Pressable>
          <View style={a.headerCopy}>
            <Text style={a.eyebrow}>{mode === 'select' ? 'TESLİMAT KONUMU' : 'HESABIM'}</Text>
            <Text style={a.title}>{editorOpen ? (draft.id ? 'Adresi düzenle' : 'Yeni adres') : 'Kayıtlı adreslerim'}</Text>
          </View>
        </View>

        {editorOpen ? (
          <ScrollView contentContainerStyle={[a.content, { paddingBottom: contentBottom }]} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
            <Text style={a.help}>Teslimatın yapılacağı adresi eksiksiz yaz. Tam adres yalnızca işlem eşleştiğinde ilgili kullanıcıyla paylaşılır.</Text>
            {!!draft.id && <View style={a.snapshotInfo}><Text style={a.snapshotInfoTitle}>Yayındaki ilanların değişmez</Text><Text style={a.snapshotInfoText}>{draft.activeListingsCount ? 'Bu adres ' + draft.activeListingsCount + ' yayındaki ilanda kullanılıyor. ' : ''}Burada yaptığın değişiklik yalnızca bundan sonra oluşturacağın ilanlarda kullanılır. Mevcut ilanların teslimat konumu korunur.</Text></View>}

            <Text style={a.label}>Adres adı</Text>
            <TextInput value={draft.label} onChangeText={label => setDraft(current => ({ ...current, label }))} placeholder="Ev, İş yeri, Depo" style={a.input} />

            <Text style={a.label}>İl</Text>
            <Pressable onPress={() => { setPickerSearch(''); setPicker('province'); }} style={a.selectInput}>
              <Text style={[a.selectValue, !draft.provinceName && a.selectPlaceholder]}>{draft.provinceName || 'İl seç'}</Text>
              <Text style={a.selectChevron}>⌄</Text>
            </Pressable>

            <Text style={a.label}>İlçe</Text>
            <Pressable onPress={openDistrictPicker} style={[a.selectInput, !draft.provinceId && a.selectInputDisabled]}>
              <Text style={[a.selectValue, !draft.districtName && a.selectPlaceholder]}>{draft.districtName || (draft.provinceId ? 'İlçe seç' : 'Önce il seç')}</Text>
              {regionsLoading ? <ActivityIndicator size="small" color={C.green} /> : <Text style={a.selectChevron}>⌄</Text>}
            </Pressable>

            <Text style={a.label}>Mahalle</Text>
            <Text style={a.fieldHelp}>Mahalle adını kendin yaz. Bu bilgi ilanda il ve ilçeyle birlikte gösterilir.</Text>
            <TextInput
              value={draft.neighborhood}
              onChangeText={neighborhood => { setDraft(current => ({ ...current, neighborhood })); setCoordinates(null); }}
              placeholder="Örn. Karpuzdere"
              style={a.input}
              autoCapitalize="words"
            />

            <Text style={a.label}>Tam teslimat adresi (gizli)</Text>
            <Text style={a.fieldHelp}>Cadde veya sokak, site/blok, bina ve daire bilgilerini eksiksiz yaz.</Text>
            <TextInput
              value={draft.fullAddress}
              onChangeText={fullAddress => { setDraft(current => ({ ...current, fullAddress })); setCoordinates(null); }}
              placeholder="Cadde/sokak, bina no, daire no"
              multiline
              style={[a.input, a.multiline]}
            />

            <View style={a.locationCard}>
              <View style={a.locationIcon}><Text style={a.locationIconText}>⌖</Text></View>
              <View style={a.locationCopy}>
                <Text style={a.locationTitle}>{coordinates ? 'Adres konumu hazır' : 'Adres kaydedilirken konumu doğrulanacak'}</Text>
                <Text style={a.locationText}>{coordinates ? 'Kilometre hesabında bu adres kullanılacak.' : 'İl, ilçe, mahalle ve tam adres kilometre hesabı için konuma dönüştürülecek.'}</Text>
              </View>
            </View>
            <Pressable disabled={locating || geocoding} onPress={() => void goToCurrentLocation()} style={a.currentLocationButton}>
              {locating ? <ActivityIndicator size="small" color={C.green} /> : <Text style={a.currentLocationButtonIcon}>⌖</Text>}
              <Text style={a.currentLocationButtonText}>Mevcut konumumu kullan</Text>
            </Pressable>
            {geocoding && <View style={a.geocode}><ActivityIndicator color={C.green} /><Text style={a.geocodeText}>Adres konumu doğrulanıyor…</Text></View>}
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


            <Pressable disabled={saving || geocoding} onPress={() => void save()} style={[a.primary, (saving || geocoding) && a.disabled]}>
              {saving ? <ActivityIndicator color="#FFFFFF" /> : <Text style={a.primaryText}>{actionLabel}</Text>}
            </Pressable>
          </ScrollView>
        ) : (
          <ScrollView contentContainerStyle={[a.content, { paddingBottom: contentBottom }]} showsVerticalScrollIndicator={false}>
            <Text style={a.help}>Açık adreslerin yalnızca sana gösterilir. İlanda yaklaşık bölge yayınlanır.</Text>
            {loading ? (
              <ActivityIndicator color={C.green} style={a.loader} />
            ) : addresses.length ? (
              addresses.map(address => (
                <View key={address.id} style={[a.card, selectedAddressId === address.id && a.cardSelected]}>
                  <Pressable disabled={mode !== 'select'} onPress={() => choose(address)} style={a.cardMain}>
                    <View style={a.pin}><Text style={a.pinText}>⌖</Text></View>
                    <View style={a.cardCopy}>
                      <View style={a.titleRow}>
                        <Text style={a.cardTitle}>{address.label}</Text>
                        {address.isDefault && <Text style={a.defaultBadge}>VARSAYILAN</Text>}{selectedAddressId === address.id && <Text style={a.selectedBadge}>SEÇİLİ</Text>}
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
                <Text style={a.emptyText}>İlk teslimat adresini ekleyip sonraki ilanlarında yeniden kullanabilirsin.</Text>
              </View>
            )}
            <Pressable onPress={startNew} style={a.primary}>
              <Text style={a.primaryText}>Teslimat adresi ekle</Text>
            </Pressable>
          </ScrollView>
        )}
        {regionPicker}
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
  content: { padding: 20 },
  help: { color: C.muted, fontSize: 12, lineHeight: 18, marginBottom: 15 },
  snapshotInfo: { padding: 14, borderRadius: 15, borderWidth: 1, borderColor: '#C8DDCE', backgroundColor: C.soft, marginBottom: 4 },
  snapshotInfoTitle: { color: C.green, fontSize: 12, fontWeight: '900' },
  snapshotInfoText: { color: C.muted, fontSize: 11, lineHeight: 17, marginTop: 4 },
  loader: { marginVertical: 40 },
  locationCard: { borderRadius: 18, borderWidth: 1, borderColor: C.line, backgroundColor: C.white, padding: 14, flexDirection: 'row', alignItems: 'center' },
  locationIcon: { width: 44, height: 44, borderRadius: 14, backgroundColor: C.soft, alignItems: 'center', justifyContent: 'center', marginRight: 12 },
  locationIconText: { color: C.green, fontSize: 23, fontWeight: '900' },
  locationCopy: { flex: 1 },
  locationTitle: { color: C.ink, fontSize: 13, fontWeight: '900' },
  locationText: { color: C.muted, fontSize: 11, lineHeight: 17, marginTop: 3 },
  currentLocationButton: { minHeight: 48, borderRadius: 15, borderWidth: 1, borderColor: C.line, backgroundColor: C.white, paddingHorizontal: 14, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, marginTop: 10 },
  currentLocationButtonIcon: { color: C.green, fontSize: 20, fontWeight: '900' },
  currentLocationButtonText: { color: C.ink, fontSize: 12, fontWeight: '900' },
  geocode: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 10 },
  geocodeText: { color: C.muted, fontSize: 12 },
  label: { color: C.ink, fontSize: 12, fontWeight: '900', marginTop: 13, marginBottom: 4 },
  fieldHelp: { color: C.muted, fontSize: 11, lineHeight: 16, marginBottom: 7 },
  input: { minHeight: 50, borderRadius: 15, borderWidth: 1, borderColor: C.line, backgroundColor: C.white, paddingHorizontal: 14, color: C.ink, fontSize: 14, fontWeight: '700' },
  selectInput: { minHeight: 50, borderRadius: 15, borderWidth: 1, borderColor: C.line, backgroundColor: C.white, paddingHorizontal: 14, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  selectInputDisabled: { backgroundColor: '#F0F3EF', opacity: .72 },
  selectValue: { color: C.ink, fontSize: 14, fontWeight: '800' },
  selectPlaceholder: { color: C.muted, fontWeight: '700' },
  selectChevron: { color: C.green, fontSize: 20, fontWeight: '900' },  multiline: { minHeight: 92, paddingTop: 13, textAlignVertical: 'top' },
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
  cardSelected: { borderColor: C.green, borderWidth: 2 },
  cardMain: { padding: 14, flexDirection: 'row', alignItems: 'center' },
  pin: { width: 42, height: 42, borderRadius: 14, backgroundColor: C.soft, alignItems: 'center', justifyContent: 'center', marginRight: 12 },
  pinText: { color: C.green, fontSize: 22, fontWeight: '900' },
  cardCopy: { flex: 1 },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: 7 },
  cardTitle: { color: C.ink, fontSize: 15, fontWeight: '900' },
  selectedBadge: { color: C.white, fontSize: 10, fontWeight: '900', backgroundColor: C.green, paddingHorizontal: 6, paddingVertical: 4, borderRadius: 7 },
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
  pickerBackdrop: { flex: 1, backgroundColor: 'rgba(12, 30, 21, .45)', justifyContent: 'flex-end' },
  pickerSheet: { maxHeight: '78%', borderTopLeftRadius: 26, borderTopRightRadius: 26, backgroundColor: C.white, paddingHorizontal: 18, paddingTop: 10 },
  pickerHandle: { width: 44, height: 5, borderRadius: 3, backgroundColor: '#CBD4CE', alignSelf: 'center', marginBottom: 15 },
  pickerHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  pickerTitle: { color: C.ink, fontSize: 21, fontWeight: '900', marginTop: 2 },
  pickerClose: { width: 40, height: 40, borderRadius: 20, backgroundColor: C.bg, alignItems: 'center', justifyContent: 'center' },
  pickerCloseText: { color: C.ink, fontSize: 24, lineHeight: 27 },
  searchInput: { minHeight: 48, borderRadius: 15, borderWidth: 1, borderColor: C.line, backgroundColor: C.bg, paddingHorizontal: 14, color: C.ink, fontSize: 14, fontWeight: '700', marginTop: 14 },
  pickerList: { marginTop: 10 },
  pickerLoader: { marginVertical: 35 },
  pickerOption: { minHeight: 50, borderBottomWidth: 1, borderBottomColor: C.line, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 5 },
  pickerOptionSelected: { backgroundColor: C.soft, borderRadius: 12, borderBottomWidth: 0, paddingHorizontal: 12, marginVertical: 2 },
  pickerOptionText: { color: C.ink, fontSize: 14, fontWeight: '800' },
  pickerOptionTextSelected: { color: C.green },
  pickerCheck: { color: C.green, fontSize: 16, fontWeight: '900' },
  pickerEmpty: { color: C.muted, fontSize: 13, textAlign: 'center', paddingVertical: 30 },});
