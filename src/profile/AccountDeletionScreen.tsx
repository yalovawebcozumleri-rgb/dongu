import React, { useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { C } from '../../styles';
import { useNotice } from '../notice/NoticeProvider';

const CONFIRMATION = 'HESABIMI SİL';

export default function AccountDeletionScreen({ back, deleteAccount }: { back: () => void; deleteAccount: (confirmation: string) => Promise<{ error?: string }> }) {
  const { confirmNotice, showNotice } = useNotice();
  const [value, setValue] = useState('');
  const [saving, setSaving] = useState(false);
  const enabled = value.trim().toLocaleUpperCase('tr-TR') === CONFIRMATION;
  const submit = async () => {
    if (!enabled || saving) return;
    const accepted = await confirmNotice({ tone: 'error', eyebrow: 'GERİ ALINAMAZ', title: 'Hesabın kalıcı olarak silinsin mi?', message: 'Aktif işlemlerin iptal edilir, kişisel bilgilerin kaldırılır ve bütün cihazlarda oturumun kapanır.', primaryLabel: 'Evet, hesabımı sil', secondaryLabel: 'Vazgeç' });
    if (!accepted) return;
    setSaving(true);
    const result = await deleteAccount(CONFIRMATION);
    setSaving(false);
    if (result.error) showNotice({ tone: 'error', title: 'Hesap silinemedi', message: result.error });
  };
  return <View style={x.screen}><View style={x.header}><Pressable onPress={back} style={x.back}><Text style={x.backText}>‹</Text></Pressable><View><Text style={x.eyebrow}>HESAP VE VERİLER</Text><Text style={x.title}>Hesabımı sil</Text></View></View><ScrollView contentContainerStyle={x.content}><View style={x.warning}><Text style={x.warningTitle}>Bu işlem geri alınamaz</Text><Text style={x.text}>Adın, e-postan, telefonun, avatarın, adreslerin ve bildirim cihaz kayıtların kaldırılır. Aktif ilan ve taleplerin sonlandırılır.</Text></View><Text style={x.section}>Anonim saklanabilecek kayıtlar</Text><Text style={x.text}>Tamamlanmış teslimatlar ile güvenlik ve ihlal kayıtları, diğer kullanıcıların işlem geçmişini ve platform güvenliğini korumak için kimliğinden arındırılmış biçimde tutulabilir.</Text><Text style={x.label}>Onaylamak için “{CONFIRMATION}” yaz</Text><TextInput autoCapitalize="characters" value={value} onChangeText={setValue} placeholder={CONFIRMATION} style={x.input} /><Pressable disabled={!enabled || saving} onPress={() => void submit()} style={[x.delete, (!enabled || saving) && x.disabled]}>{saving ? <ActivityIndicator color="#fff" /> : <Text style={x.deleteText}>Hesabımı kalıcı olarak sil</Text>}</Pressable><Pressable onPress={back} style={x.cancel}><Text style={x.cancelText}>Vazgeç</Text></Pressable></ScrollView></View>;
}

const x = StyleSheet.create({ screen:{flex:1,backgroundColor:C.bg},header:{flexDirection:'row',alignItems:'center',padding:20,borderBottomWidth:1,borderColor:C.line},back:{width:44,height:44,borderRadius:15,backgroundColor:C.white,alignItems:'center',justifyContent:'center',marginRight:13},backText:{fontSize:30,color:C.ink},eyebrow:{fontSize:11,fontWeight:'900',letterSpacing:1.3,color:C.green},title:{fontSize:23,fontWeight:'900',color:C.ink,marginTop:3},content:{padding:20,paddingBottom:40},warning:{backgroundColor:'#FFF0EC',borderWidth:1,borderColor:'#F0C8BE',borderRadius:20,padding:19},warningTitle:{fontSize:17,fontWeight:'900',color:'#9B3F30',marginBottom:7},section:{fontSize:14,fontWeight:'900',color:C.ink,marginTop:22,marginBottom:7},text:{fontSize:13,lineHeight:21,color:C.muted},label:{fontSize:13,fontWeight:'900',color:C.ink,marginTop:24,marginBottom:8},input:{height:54,borderRadius:16,borderWidth:1,borderColor:C.line,backgroundColor:C.white,paddingHorizontal:16,fontSize:15,fontWeight:'800',color:C.ink},delete:{height:56,borderRadius:17,backgroundColor:'#A23D32',alignItems:'center',justifyContent:'center',marginTop:18},disabled:{opacity:.45},deleteText:{color:'#fff',fontSize:14,fontWeight:'900'},cancel:{height:48,alignItems:'center',justifyContent:'center'},cancelText:{color:C.green,fontSize:13,fontWeight:'900'} });

